# Releasing

Releases are published to [Packagist](https://packagist.org/packages/shopware/deployment-helper) automatically when a semantic version tag is pushed. Do not release manually.

## Create a release

1. Ensure the commit to release is on the intended branch and all CI checks have passed (PHP tests, PHPStan, code style).
2. Create an annotated [semantic version](https://semver.org/) tag, for example:

   ```sh
   git tag -a v1.2.3 -m "v1.2.3"
   ```

   Use a prerelease suffix such as `v1.2.3-rc.1` for prereleases.
3. Push only the new tag:

   ```sh
   git push origin v1.2.3
   ```

Pushing the tag triggers Packagist to mirror the release. Verify the release on [Packagist](https://packagist.org/packages/shopware/deployment-helper)

4. Go to [GitHub Releases](https://github.com/shopware/deployment-helper/releases):
  - create a new release based on the pushed tag
  - auto generate the release notes
  - publish it and confirm it's visible (e.g. not a draft)

## If a release fails

Do not move or reuse a published version tag; create a new version tag for a corrected release.
