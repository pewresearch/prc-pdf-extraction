# PRC PDF Extraction

Generic WordPress plugin for extracting text from PDF documents using OCR/AI providers, making content accessible to LLMs via custom endpoints. Post type, URL slug, labels, and extraction prompts are all filterable so the plugin can be adapted to any use case without forking.

## Pew Research Center configuration

PRC uses this plugin to process **survey topline documents**. The `prc-platform-core` AI module overrides the generic defaults via filters so that the topline workflow is transparent to the rest of the platform:

| Filter                                | PRC value                 | Generic default                  |
| ------------------------------------- | ------------------------- | -------------------------------- |
| `prc_pdf_extraction_post_type`        | `topline`                 | `pdf_extraction`                 |
| `prc_pdf_extraction_url_slug`         | `topline`                 | `extraction`                     |
| `prc_pdf_extraction_labels`           | Topline / Toplines        | PDF Extraction / PDF Extractions |
| `prc_pdf_extraction_markdown_prompt`  | PRC survey topline prompt | Generic PDF prompt               |
| `prc_pdf_extraction_gutenberg_prompt` | PRC survey topline prompt | Generic PDF prompt               |

These filters are registered in `plugins/prc-platform-core/includes/ai/class-ai.php` inside `register_pdf_extraction_filters()`. If `prc-platform-core` is not active, the plugin falls back to the generic defaults above.

## Filter API

All filters fire at registration time, before the post type or rewrite rules are flushed.

```php
// Override post type slug
add_filter( 'prc_pdf_extraction_post_type', fn() => 'topline' );

// Override URL endpoint slug  (/extraction → /toplines)
add_filter( 'prc_pdf_extraction_url_slug', fn() => 'toplines' );

// Override labels
add_filter( 'prc_pdf_extraction_labels', function ( array $labels ): array {
    return array_merge( $labels, [
        'name'          => 'Toplines',
        'singular_name' => 'Topline',
    ] );
} );

// Override extraction prompts
add_filter( 'prc_pdf_extraction_markdown_prompt', fn() => 'Your custom prompt...' );
add_filter( 'prc_pdf_extraction_gutenberg_prompt', fn() => 'Your custom prompt...' );

// Tune extraction thresholds
add_filter( 'prc_pdf_extraction_min_chars', fn() => 2000 );
add_filter( 'prc_pdf_extraction_max_validation_retries', fn() => 3 );
```

## Installation

```bash
wp plugin activate prc-pdf-extraction
```

## WP-CLI

```bash
# List available OCR providers
wp prc-pdf-extraction list-providers

# Process a specific post
wp prc-pdf-extraction process --post_id=123

# Test extraction against a local PDF file
wp prc-pdf-extraction test_file --file=/path/to/document.pdf --show-markdown

# Force re-process even if extraction already exists
wp prc-pdf-extraction process --post_id=123 --force

# Dry run (shows what would be processed, no save)
wp prc-pdf-extraction process --post_id=123 --dry-run

# Target a specific material by index (0-based)
wp prc-pdf-extraction process --post_id=123 --material_index=1
```

## OCR Providers

Three providers are supported. Providers are tried in priority order; the first to succeed wins.

| Provider           | Priority    | Auth           | Multi-page | Markdown |
| ------------------ | ----------- | -------------- | ---------- | -------- |
| Claude (Anthropic) | 5 (highest) | API Key        | ✅         | ✅       |
| Gemini (Google)    | 10          | API Key        | ✅         | ✅       |
| WP AI              | 15          | WP site config | varies     | varies   |

### Claude setup

```php
// vip-config/vip-env-vars.local.php
define( 'VIP_ENV_VAR_PRC_PLATFORM_ANTHROPIC_API_KEY', 'sk-ant-...' );
```

### Gemini setup

1. Get an API key at [Google AI Studio](https://aistudio.google.com/apikey).

```php
define( 'VIP_ENV_VAR_PRC_PLATFORM_GOOGLE_API_KEY', 'AIzaSy...' );
```

### WP AI setup

WP AI uses whatever AI provider is configured in the WordPress admin. No additional constants are required.

### Troubleshooting

| Symptom                  | Likely cause                                                 |
| ------------------------ | ------------------------------------------------------------ |
| "No providers available" | API keys missing or malformed                                |
| Very low character count | Only the first page was processed                            |
| Validation failures      | Prompt mismatch — check `prc_pdf_extraction_markdown_prompt` |

## Architecture

```text
prc-pdf-extraction/
├── prc-pdf-extraction.php           # Main plugin file
├── includes/
│   ├── class-bootstrap.php          # Plugin initialization
│   ├── class-loader.php             # Hook management
│   ├── class-content-type.php       # Post type registration (filterable)
│   ├── class-rewrite-rules.php      # URL rewrite rules (filterable slug)
│   ├── class-rest-api.php           # REST endpoints (/prc-pdf-extraction/v1/)
│   ├── class-action-scheduler-handler.php
│   ├── class-extraction-service.php
│   ├── class-admin.php
│   ├── class-wp-cli-commands.php
│   ├── class-bulk-cli-command.php
│   ├── class-content-discovery.php
│   ├── utils.php                    # Helper functions
│   └── ocr/
│       ├── application/
│       │   ├── class-ocr-orchestrator.php
│       │   ├── class-extraction-formatter.php
│       │   ├── class-quality-validator.php
│       │   ├── class-output-validator.php
│       │   ├── class-page-analyzer.php
│       │   └── class-result-merger.php
│       ├── domain/
│       │   └── class-ocr-response.php
│       └── providers/
│           ├── trait-shared-extraction-prompts.php  # Generic prompts (filterable)
│           ├── class-claude-provider.php
│           ├── class-gemini-provider.php
│           └── class-wp-ai-provider.php
├── template-extraction.php          # Front-end template
└── tests/
    ├── bootstrap.php
    ├── test-plugin.php
    ├── test-content-type.php
    ├── test-content-discovery.php
    ├── test-admin.php
    ├── test-extraction-service.php
    ├── test-action-scheduler-handler.php
    ├── test-utils.php
    └── ocr/
        ├── test-claude-provider.php
        ├── test-quality-validator.php
        └── test-output-validator.php
```

### Post type

The post type slug is `pdf_extraction` by default, overridden to `topline` in PRC via filter.

**Supports:** title, editor, excerpt, revisions, custom-fields, page-attributes (parent-child relationships)

**Meta fields:**

| Field                       | Description                                           |
| --------------------------- | ----------------------------------------------------- |
| `_pdf_source_attachment_id` | Source PDF attachment ID                              |
| `_pdf_source_url`           | Source PDF URL                                        |
| `_pdf_source_label`         | Label from `reportMaterials`                          |
| `_ocr_provider_used`        | Provider that succeeded (`claude`, `gemini`, `wp-ai`) |
| `_ocr_confidence_score`     | Confidence score (0–1)                                |
| `_extraction_date`          | ISO timestamp of extraction                           |
| `_extraction_version`       | Plugin version used                                   |
| `_extracted_text_plain`     | Plain text content                                    |
| `_extracted_text_markdown`  | Markdown-formatted content                            |
| `_validation_status`        | `passed` / `warning` / `failed`                       |
| `_validation_issues`        | Array of validation issue strings                     |
| `_processing_cost_usd`      | Estimated cost in USD                                 |
| `_character_count`          | Total character count                                 |

### REST API

Base namespace: `prc-pdf-extraction/v1`

| Endpoint   | Method | Description                                |
| ---------- | ------ | ------------------------------------------ |
| `/convert` | `POST` | Trigger extraction for a post + attachment |

### URL endpoints

With the generic default slug (`extraction`):

- `/{post-slug}/extraction` — markdown output
- `/{post-slug}/text` — plain text output

With the PRC filter (`topline`):

- `/{post-slug}/topline` — markdown output
- `/{post-slug}/text` — plain text output

## Testing

```bash
# Install dependencies
cd plugins/prc-pdf-extraction
composer install

# Run all tests (local)
composer test

# Run all tests inside VIP Docker container
npm run test:php

# Run specific test file
vip dev-env shell --slug=prc-platform -- bash -c \
  "cd /wp/wp-content/plugins/prc-pdf-extraction && \
   vendor/bin/phpunit --configuration phpunit-vip.xml.dist \
   --filter=test_post_type_registered"

# Multisite
composer test-ms
```

### Test coverage

| Suite              | Tests  | Notes                                                                                                     |
| ------------------ | ------ | --------------------------------------------------------------------------------------------------------- |
| Plugin core        | 3      | Constants, class existence                                                                                |
| Content type       | 8      | Post type registration, meta fields                                                                       |
| Utility functions  | 7      | `get_extraction_materials`, `get_extraction_material_by_index`, `validate_extraction_material_attachment` |
| Admin              | 14     | Meta boxes, labels, capabilities                                                                          |
| Extraction service | 20     | Material parsing, save/update, cleanup                                                                    |
| Action Scheduler   | 6      | Schedule, process, idempotency                                                                            |
| Content discovery  | 6      | Alternate links, JSON-LD, robots.txt                                                                      |
| OCR providers      | varies | Claude, quality validator, output validator                                                               |

19 tests are skipped on PHP 8.2+ due to a WordPress core serialization bug (`SERIALIZATION_FORMAT_USE_UNSERIALIZE`). All affected functionality works correctly in production.

## License

GPL-2.0+

## Credits

Developed by Pew Research Center
