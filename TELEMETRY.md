# Telemetry

The Shopware Deployment Helper includes a telemetry feature that collects anonymous usage data. This data helps us understand how the tool is used and how we can improve it.

## What is being tracked?

We track specific events that occur during the deployment process. All data is anonymized and does not contain any sensitive information like passwords, secrets, or personal data.

### Common Data

Every tracking event includes the following common information:

*   **Anonymized User ID**: A randomly generated UUID stored in your shop's configuration (`core.deployment_helper.id`). This allows us to correlate events from the same installation without knowing who the user is.
*   **Shopware Version**: The version of Shopware being deployed.
*   **Timestamp**: The date and time when the event occurred.

### Events

The following events are currently tracked:

| Event            | Data Collected                                | Description                                             |
|------------------|-----------------------------------------------|---------------------------------------------------------|
| `php_version`    | `php_version` (e.g., `8.3`)                   | The PHP version used to run the helper.                 |
| `mysql_version`  | `mysql_version`                               | The version of the MySQL/MariaDB database.              |
| `installed`      | `took` (seconds), `shopware_version`          | Sent when a new Shopware installation is completed.     |
| `upgrade`        | `took` (seconds), `previous_shopware_version` | Sent when an existing Shopware installation is updated. |
| `theme_compiled` | `took` (seconds)                              | Sent after the theme has been successfully compiled.    |

## How to disable telemetry

If you prefer not to share this information, you can disable telemetry by setting the `DO_NOT_TRACK` environment variable to `1`.

### Example (Shell)

```bash
export DO_NOT_TRACK=1
./vendor/bin/shopware-deployment-helper run
```
