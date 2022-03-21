`fs-mautic-hooks` is a webhook receiver for [Freemius](https://freemius.com/) that will update [Mautic](https://www.mautic.org/) contacts on specified webhook events.

This is intended to be used with the [fs-mautic-sync](https://github.com/koen12344/fs-mautic-sync) initial sync script to set up the custom fields in Mautic and do an initial sync of the plugin users.

This script is formatted as a Google App Engine app but could be adapted to work on different serverless platforms (or just a good ole lamp server). Feel free to fork.

## Features
* Uses secure Mautic oAuth2 authentication
* Creates or updates Mautic contacts and companies when:
  * A new plugin user is created
  * The plugin is installed on a new site
  * The plugin is deactivated/uninstalled
  * The plugin plan is changed
  * The user opts in/out of the beta program
  * The user opts in/out of marketing emails
  * The user is accepted as an affiliate
  * The PHP, WordPress or plugin version are updated

## Requirements
* [gcloud cli](https://cloud.google.com/sdk/docs/install) must be installed in PATH
* App engine app [must be configured](https://cloud.google.com/sdk/gcloud/reference/app/create) in your cloud project

## Installation & usage
1. Clone the repository
2. Create a new `config.php` file in the root directory based on `config.sample.php`
3. Modify the `app.yaml` to suit your needs
4. Run `gcloud app deploy` to deploy the app to Google App Engine
5. Navigate to `https://path-to-your-app-engine-app.appspot.com/authorize` to initialize the oAuth flow
6. Add a new webhook in Freemius, pointing to `https://path-to-your-app-engine-app.appspot.com/?token=the-token-you-defined-in-config.php`

## Troubleshooting
* Try clearing the Mautic cache if the API is giving you issues