Where the site is developed, how to report a problem, and how these pages are published.

- [Repository](https://github.com/UAMS-Web/uams-statamic): the code, issues and pull requests.
- [Report a problem](https://github.com/UAMS-Web/uams-statamic/issues): open an issue.
- [Releases](https://github.com/UAMS-Web/uams-statamic/releases): one prerelease a business day when there is unreleased work, until the 1.0 launch.

## How these pages are published

The pages are written in [`docs/wiki/`](https://github.com/UAMS-Web/uams-statamic/tree/main/docs/wiki) and published to this wiki by the [Publish wiki](https://github.com/UAMS-Web/uams-statamic/blob/main/.github/workflows/publish-wiki.yml) workflow, which runs when a release is published and can be run by hand. Each run replaces the whole wiki with that folder, so a page edited here in the browser is overwritten. Change a page through a pull request instead.
