{{-- Project override of vendor/laravel/boost/.ai/deployments/core.blade.php. Kept in step with the
     vendor template except that the line telling agents to activate the `deploying-to-cloud` skill
     is left out: boost.json installs no such skill, and this repository does not deploy to Laravel
     Cloud (UAMS-Web/uams-statamic-archive#2708). A same-named file here replaces the vendor file whole, so the vendor's first line
     is carried over unchanged. --}}
# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
