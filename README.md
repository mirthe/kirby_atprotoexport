# Kirby ATProto Publisher

A Kirby CMS plugin to export blog posts and publish their metadata to AT Protocol (ATProto), making it possible to share content on services such as Bluesky and Eurosky.

## About Standard.site

[Standard.site](https://standard.site/) provides shared lexicons for long-form publishing on the AT Protocol (ATProto).

It defines a common format for publications (`site.standard.publication`) and individual articles (`site.standard.document`), making blog content easier to discover, index and share across platforms in the AT Protocol ecosystem.

This plugin publishes Kirby blog posts as Standard.site documents, linking them to an existing publication record. This allows other ATProto-compatible platforms to discover and display your content.

Learn more: https://standard.site/ or https://hannahshelley.neocities.org/blog/2026_05_31_ATProto

## Features

- Export Kirby blog posts to JSON.
- Select posts by publication date or tag.
- Include metadata such as title, description, category, tags and publication date.
- Use Kirby UUIDs as ATProto record keys.
- Publish records using the ATProto API.
- Automatically detect existing records and update them when necessary.
- Batch record creation and updates using `com.atproto.repo.applyWrites`.

## Requirements

- Kirby CMS 5.x
- PHP with cURL enabled
- An account on an ATProto-compatible service (such as Bluesky or Eurosky)

No external Composer dependencies are required.

## Installation

Install as a Git submodule:

```bash
git submodule add https://github.com/mirthe/kirby_atprotoexport.git site/plugins/kirby_atprotoexport
```

Alternatively, clone the repository directly into `site/plugins/`.

## Configuration

Add the following configuration to your Kirby configuration file (`config.php`):

```php
return [
    'standard-site' => [
        'repo'        => 'your-handle',
        'publication' => 'your-publication-record-key',
        'pds'         => 'https://eurosky.social',
        'identifier'  => 'your-handle',
        'password'    => 'your-app-password'
    ]
];
```

Use an app password rather than your regular account password whenever possible.

## Export

The exporter collects blog posts from the Kirby site and generates a JSON file named:

`standard-site-export.json`

The export contains:

- Repository identifier
- Collection (record type)
- Records containing:
  - Record key (Kirby UUID)
  - Record type
  - Site URL
  - Title
  - Description
  - Category and tags
  - Page path
  - Publication date

The publishing endpoint is available through the Kirby panel:

`/standard-site/export`

### Selecting posts

The export supports two selection methods:

- **Date:** export posts published on or after a specified date.
- **Tag:** export posts containing a specified tag.

The selection method and its parameters can be configured in the export script.

## Publishing

Publishing uses the ATProto API.

The plugin:

1. Authenticates with the configured PDS using `com.atproto.server.createSession`.
2. Checks whether each record already exists using `com.atproto.repo.getRecord`.
3. Creates or updates records using `com.atproto.repo.applyWrites`.

The publishing endpoint is available through the Kirby panel:

`/standard-site/publish`

The publishing routes are restricted to authenticated panel users.

## API

The plugin communicates with the following ATProto endpoints:

| Endpoint | Purpose |
|---|---|
| `com.atproto.server.createSession` | Authentication |
| `com.atproto.repo.getRecord` | Check existing records |
| `com.atproto.repo.applyWrites` | Create or update records |

## License

MIT License.

Copyright (c) Mirthe Valentijn.
