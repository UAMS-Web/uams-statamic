This wiki documents how the UAMS Statamic site is set up, deployed and run: its git submodules, the deploy scripts and web-server settings, the review step for changes to `people` entries, and the problems people meet most often. The [README](https://github.com/UAMS-Web/uams-statamic#readme) keeps the overview, the installation steps and the license.

The pages are written in [`docs/wiki/`](https://github.com/UAMS-Web/uams-statamic/tree/main/docs/wiki) and published from there, so a change goes through a pull request like any other.

## Getting started

- [Installation (README)](https://github.com/UAMS-Web/uams-statamic#installation-instructions): set up a local copy of the site with Laravel Herd.
- [Git submodules](Git-submodules.md): the five submodules that hold content, users, forms and assets, and what a clone without access to them can still do.

## Guides

- [Deploying](Deploying.md): the deploy scripts for Ploi and Forge, step by step.
- [Working copies of people entries](Working-copies-of-people-entries.md): turning on the review step for `people` entries, and keeping pending changes across deploys.

## Troubleshooting

- [Troubleshooting](Troubleshooting.md): one entry per symptom, with its cause and fix.

## Reference

- [Web server configuration](Web-server-configuration.md): the NGINX settings that let browsers cache static files.
- [Repository documentation](Repository-documentation.md): where the decision log, runbooks, specifications and schema notes live in the repository.

## Project

- [Project](Project.md): where the site is developed and how these pages are published.
- [Report a problem](https://github.com/UAMS-Web/uams-statamic/issues): open an issue.
- [Releases](https://github.com/UAMS-Web/uams-statamic/releases): the release notes.
