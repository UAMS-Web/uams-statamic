# SanitizeBardContent Command Documentation

## Overview

The `SanitizeBardContent` command is an Artisan command that scans and fixes Bard content containing YAML document separators that cause parsing errors in Statamic.

## Problem Context

When a Bard field contains text nodes with YAML document separators (`...` or `---`) as the last content, Statamic throws the error:

```
Invalid content, text values must be strings
```

This occurs because YAML parsers interpret `...` and `---` as document terminators rather than literal string values when they appear at the end of content.

### Examples of Problematic Content

**This causes an error:**
```yaml
content:
  -
    type: paragraph
    content:
      -
        type: text
        text: ...
```

**This works fine:**
```yaml
content:
  -
    type: paragraph
    content:
      -
        type: text
        text: ...
  -
    type: paragraph
    content:
      -
        type: text
        text: ABC
```

**This also works fine (quoted):**
```yaml
content:
  -
    type: paragraph
    content:
      -
        type: text
        text: '...'
```

## Command Usage

### Basic Usage

```bash
# Check what would be changed (dry run)
php artisan bard:sanitize --dry-run

# Apply changes to all collections
php artisan bard:sanitize

# Apply changes to a specific collection
php artisan bard:sanitize --collection=articles
```

### Command Options

- `--collection=`: Only process entries from a specific collection
- `--dry-run`: Show what would be changed without making changes

### Command Output

The command provides detailed output showing:

1. **Processing Progress**: Lists each collection being processed
2. **Issue Detection**: Shows entries with Bard content needing sanitization
3. **Summary Report**: 
   - Total entries processed
   - Entries with YAML separator issues
   - Entries updated (if not in dry-run mode)

### Example Output

```
Scanning for Bard content that needs sanitization...
Processing collection: Articles
  - Entry 'Test Article' has Bard content needing sanitization
Processing collection: Pages
  - Entry 'Home Page' has Bard content needing sanitization

Summary:
  - Total entries processed: 150
  - Entries with YAML separator issues: 2
  - Entries updated: 2
```

## How the Command Works

1. **Collection Discovery**: Identifies all collections or a specific collection
2. **Entry Scanning**: Iterates through all entries in the collection(s)
3. **File Analysis**: Reads raw YAML files to detect problematic content patterns
4. **Pattern Matching**: Uses regex patterns to find unquoted YAML separators in text fields
5. **File Sanitization**: Directly modifies YAML files to escape separators with quotes
6. **File Updates**: Writes sanitized content back to the original files

## Integration with Automatic Solution

This command works alongside the automatic sanitization system:

### Automatic Protection
- **BardContentSanitizerServiceProvider**: Hooks into entry saving to automatically sanitize files after save
- **Real-time Protection**: New content is automatically protected when entries are saved

### Manual Cleanup
- **This Command**: Cleans up existing problematic content in YAML files
- **One-time Operation**: Typically run once after deployment or as needed
- **Safe Operation**: Dry-run mode allows testing before applying changes

## What Gets Fixed

The command automatically escapes these YAML document separators in text fields:

- `text: ...` → `text: '...'`
- `text: ---` → `text: '---'`

## Safety Features

1. **Dry-run Mode**: Test changes without modifying files
2. **Collection Targeting**: Process only specific collections
3. **Detailed Logging**: See exactly what will be changed
4. **Error Handling**: Graceful handling of missing files or collections

## Best Practices

### Before Running

1. **Backup Your Data**: Always backup your content before running the command
2. **Test with Dry-run**: Use `--dry-run` first to see what will be changed
3. **Target Specific Collections**: Use `--collection` to limit scope
4. **Review Output**: Carefully review the detected issues

### After Running

1. **Verify Changes**: Check that content displays correctly
2. **Test Functionality**: Ensure Bard fields work as expected
3. **Monitor for Issues**: Watch for any new problematic content

## Troubleshooting

### Command Not Found

Ensure the command is properly registered:

```bash
php artisan list | grep bard
```

### Collection Not Found

Verify the collection handle is correct:

```bash
php artisan bard:sanitize --collection=invalid-collection
# Error: Collection 'invalid-collection' not found.
```

### No Changes Made

If no entries are updated:

1. Check if entries actually contain unquoted YAML separators
2. Verify the YAML files are accessible
3. Ensure content is in the expected format

### Performance Issues

For large datasets:

1. Process collections individually using `--collection`
2. Run during low-traffic periods
3. Monitor server resources during execution

## Related Components

### Services
- `app/Services/BardContentSanitizer.php` - Core sanitization logic (used by service provider)

### Providers
- `app/Providers/BardContentSanitizerServiceProvider.php` - Automatic file-level sanitization

## Technical Implementation

### File-Level Processing

The command processes YAML files directly rather than working with parsed entry data:

1. **Raw File Access**: Uses `$entry->path()` to get file locations
2. **Pattern Matching**: Uses regex to find `text: ...` and `text: ---` patterns
3. **Direct Replacement**: Replaces patterns with quoted versions
4. **File Writing**: Writes sanitized content back to files

### Automatic Protection

The service provider hooks into Statamic's `EntrySaved` event:

1. **Event Listening**: Listens for `EntrySaved` events
2. **File Checking**: Reads saved files to check for YAML separators
3. **Automatic Sanitization**: Applies sanitization if needed
4. **File Updates**: Updates files with sanitized content

## Upstream Monitoring

This is a workaround for a bug in the interaction between Statamic's YAML parsing and Tiptap's Bard editor. Developers should monitor:

- **Statamic CMS updates** for YAML parsing improvements
- **Tiptap editor updates** for Bard field handling
- **Symfony YAML component updates** for parser behavior changes

This command may become unnecessary or require modification if upstream libraries implement proper handling of YAML separators in content fields. 
