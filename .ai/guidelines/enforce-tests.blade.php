{{-- Project override of vendor/laravel/boost/.ai/enforce-tests.blade.php. laravel/boost 2.10.0
     relaxed this section so that copy, styling and layout-only changes need no tests, and added a
     line telling agents to read the `testing-best-practices` skill, which boost.json does not
     install. This repository keeps the stricter rule it had at 2.5.0 (UAMS-Web/uams-statamic-archive#2708), so this file restores
     that wording. A same-named file here replaces the vendor file whole. --}}
# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.
