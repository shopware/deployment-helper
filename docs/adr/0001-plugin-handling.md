# 1. Plugin handling: load, plan, execute

- Status: Accepted
- Date: 2026-06-17

## Context

During installation and upgrade the Deployment Helper brings every Shopware plugin
into the state the project configuration asks for: install, activate, update,
deactivate or remove it. Each of those actions is ultimately a Shopware console
command, and each console command starts a fresh subprocess that boots the Shopware
kernel.

Four properties of the problem shaped the design:

1. **Order matters.** A plugin that depends on another must be acted on after its
   dependency. The plugins therefore have to be sorted into dependency order before
   anything runs.
2. **Starting a process is expensive.** Because every console command boots the
   kernel, doing one command per plugin dominates deployment time on stores with many
   plugins. Doing fewer, larger commands is the main performance lever.
3. **Dependency information has to be gathered from the environment.** Which plugin
   depends on which is not given directly; it is derived from the plugins' own
   metadata and the project's lock file. This is the only part of the work that
   touches the outside world on the *input* side.
4. **The decisions themselves are pure.** Given the list of plugins, their reported
   state, and the project configuration, *which* command each plugin needs can be
   decided with no I/O at all. The only I/O is reading the inputs and running the
   results.

The earlier implementation interleaved all of this: a single component looped over
the plugins and ran console commands inline. The ordering and batching rules were
therefore entangled with process execution, which made them awkward to reason about
and to test — verifying a rule meant observing a sequence of side effects rather than
inspecting a decision.

## Decision

Separate plugin handling into three roles, each independently testable:

```
   inputs                decision               effect
   ──────                ────────               ──────
  loader   ──►  plugin set  ──►  planner  ──►  plan  ──►  executor
(gather &        (ordered,      (pure: state +  (ordered    (run each
 resolve)        with dep        config →        list of      command)
                 metadata)       commands)       commands)
```

### Loading

The **loader** gathers the installed plugins, works out the dependency relationships
between them, and returns them in dependency order together with, for each plugin,
whether it depends on any other plugin. This is the only step that inspects the
environment to discover structure.

### The plugin set

The result of loading is a value that carries the plugins in resolved order plus the
dependency facts the planner needs. It is plain data — no behaviour beyond answering
"is this plugin in dependency order" and "does this plugin depend on another".

### Planning

The **planner** is pure. Given the plugin set and the project configuration, it
produces an ordered list of commands to run, and nothing else — no processes, no
files, no output. Each lifecycle operation (install, update, deactivate, remove) is a
separate planning method. Because planning is pure, every rule it encodes — what to
skip, what to merge, what order to emit — can be asserted by inspecting the plan.

### The plan

A plan is an ordered list of **command objects**. Each command object knows only how
to describe itself as a concrete console invocation. The plan is therefore a complete,
inspectable description of what *would* run, decoupled from actually running it.

### Executing

The **executor** holds the public lifecycle entry points. Each one loads the plugin
set, asks the planner for a plan, and runs the plan command by command. It contains no
decisions of its own; it is the only role that performs the side effects.

The two halves have disjoint concerns: the planner depends on configuration only, the
executor on loading and process execution only. That separation is what makes the
split worthwhile rather than ceremony.

## Dependency model

The loader treats plugin dependencies as a directed graph and resolves it into a
linear order where every dependency comes before the plugins that need it. Several
properties of this model are load-bearing for the batching rule and are worth stating
plainly:

- **A dependency only counts when both plugins are present.** A plugin "depends on
  another plugin" only if the thing it requires is itself one of the plugins being
  managed. Requiring the platform, a library, or a plugin that is not currently
  present establishes no dependency.
- **Only the side that requires is marked.** Being depended *upon* does not, by
  itself, mark a plugin as having dependencies — only requiring another plugin does.
- **Indirect dependencies are respected.** The ordering is transitive: if A needs B
  and B needs C, the order is C, then B, then A — not just the direct pairs.
- **Packaging indirection is reconciled.** A plugin may be published under a different
  package identity than the one its peers require it by; the loader reconciles these
  aliases so the dependency is still recognised.
- **Cycles are fatal, by design.** If the plugins form a dependency cycle there is no
  valid order, and the run is aborted rather than guessing one. Plugin dependencies
  are expected to be acyclic.

## Merging installs into one call

Installing plugins is where merging pays off, because installs are the bulk of the
work and a single install command accepts many plugins at once. The planner therefore
**batches** installs: it accumulates plugins that can be installed together into one
command instead of emitting a command each.

Plugins are merged into a batch only when they are safe to install **in any order
relative to each other** and need no extra handling. Concretely, a plugin joins the
batch only if it is to be freshly installed, is *not* also being activated, and does
*not* depend on another managed plugin. Anything that needs special handling — a
plugin that must be activated as part of installation, or one that depends on another
plugin — is emitted as its own command. Emitting any such standalone command first
flushes whatever batch has accumulated, and the final batch is flushed at the end.

So a run reads as a few large install commands interleaved with the occasional
standalone install or activation:

```
install A, B, C         ← merged: order among them does not matter
install D (activate)    ← standalone, so the batch is flushed first
install E               ← standalone: E depends on another plugin
activate F              ← standalone
install G, H            ← next merged batch
```

### Why merging never breaks dependency order

The ordering guarantee — *a plugin is never installed before something it depends on*
— follows from two facts working together:

- the plugins arrive in dependency order, so a dependency always comes before its
  dependents; and
- a plugin that depends on another is never merged into a batch — it is standalone,
  and standalone commands flush the pending batch before they run.

Take A needs B needs C. The resolved order is C, B, A. C depends on nothing, so it
goes into a batch. B depends on C, so it is standalone: it flushes the batch
(installing C) and then installs B. A is likewise standalone and installs last. C
before B before A — order preserved.

The key insight is that merging only ever *defers* a plugin that depends on nothing,
and a plugin that depends on nothing is never something else's prerequisite-ordering
problem in the wrong direction: a later plugin that needs it is always standalone and
flushes it first. Merging plugins that have no inter-dependencies is safe regardless
of the order they end up in, which is exactly the set the planner allows into a batch.

### Why activation is kept separate

A plugin that must be installed *and* activated is never merged. Activation stays an
explicit, standalone step so a deployment never activates a plugin as an incidental
side effect of being grouped with unrelated installs.

### The cost: a merged install fails as one unit

Merging trades per-plugin failure isolation for speed. When plugins were installed one
at a time, a failure pointed at the exact plugin and left the earlier ones done. A
merged install fails as a single operation with a combined error, and how much of the
batch took effect is up to Shopware. This is acceptable because the whole operation is
**idempotent**: a re-run skips plugins that are already installed or already active, so
a retried deployment converges on the desired state.

## Consequences

### Positive

- **The rules are testable as decisions, not side effects.** Ordering, skipping and
  batching are verified by inspecting the plan a pure function returns, without
  driving real processes. The executor needs only a little wiring coverage.
- **Faster deployments.** Independent installs collapse into single commands instead
  of one process per plugin — the original motivation.
- **The plan is inspectable.** Because a plan is data, it can be logged or, in future,
  shown as a dry run without executing anything.
- **Clean separation of concerns.** Deciding and doing are different roles with
  non-overlapping dependencies, which keeps each simple.

### Negative / trade-offs

- **More moving parts.** A dedicated planner, a plugin-set value, and per-action
  command objects replace what was inline execution. The indirection is the price of
  the testability and the ordering guarantee.
- **Merged installs lose failure isolation.** As above — mitigated by idempotent
  re-runs.
- **Dependency facts require environment files in tests.** Because dependencies are
  discovered from on-disk metadata, tests that exercise resolution must provide that
  metadata. A test helper builds a throwaway project per scenario rather than relying
  on committed fixture files.
- **A dependency cycle aborts the run.** Intended, but called out so the failure mode
  is not surprising.
