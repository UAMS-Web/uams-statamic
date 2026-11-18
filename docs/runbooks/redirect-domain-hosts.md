# Moving redirect domain rules onto the domain list

`uams-web/aether-redirects` 0.4.0 (#668, UAMS-Web/aether-redirects#24) changes a `redirect_domains` rule's `host` from a domain written as text to the id of a record in the `redirect_hosts` domain list. A rule saved before the change still holds text. It serves only while a published record names its domain, and it cannot be saved again until its `host` names a record.

**Until the migration runs, those rules do not serve.** Right after the deploy the domain list is empty, so no host string resolves: the rules are left out of the edge redirect manifest, and their domains are refused as unknown hosts. Run the migration on every environment whose content holds `redirect_domains` entries written before this change, straight after deploying the release that requires `uams-web/aether-redirects` 0.4.0. An environment whose redirect rules all came from an importer run that writes record ids (UAMS-Web/wordpress-importer#347) has nothing to migrate, and the command reports that.

## Steps

1. Report what would change, without creating records or saving entries:

   ```
   php artisan redirects:migrate-domain-hosts --dry-run
   ```

   It prints `Dry run: N domain record(s) created, N entries rewritten, N skipped.` A skipped entry has a `host` that is not a valid domain. The command names each one, and leaves it as it is.

2. Run it:

   ```
   php artisan redirects:migrate-domain-hosts
   ```

   It creates one published `redirect_hosts` record for each distinct domain the rules hold, unless a published record already names it, and rewrites each rule's `host` to that record's id. Each rule then serves the redirect it served before the upgrade.

3. Run it again. The second run reports `0 domain record(s) created, 0 entries rewritten`, because a rule that already holds a record id is skipped.

4. Fix each skipped entry by hand in the Control Panel: add the domain to the domain list if it is one UAMS uses, and choose it on the rule.

5. Regenerate the edge redirect manifests (`php artisan redirects:edge-manifest`) if they were generated between the deploy and the migration, since a manifest built then leaves out the rules that were not yet migrated.

## Who may change the domain list

Creating or changing a `redirect_hosts` record needs the `manage redirect domains` permission. No role holds it, so only super users maintain the list (#668). The permission is registered by the add-on, so a role can be given it later without a code change. A save with no signed-in user, such as this command or an import, is not checked for it.

## Verified

`tests/Feature/Redirects/TrustedRedirectDomainsTest.php` runs this procedure against a rule saved with a host string: before the migration the rule's domain is refused, the dry run reports one record and one entry and changes nothing, the run creates the record and rewrites the rule, and the rule then serves its redirect.

Against the content this branch carries, the dry run reports `0 domain record(s) created, 0 entries rewritten, 0 skipped.`: the content repository holds the `redirect_domains` collection file and no entries.
