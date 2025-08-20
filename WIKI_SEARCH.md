# Wiki Search Implementation

## Overview
This application now features a complete Algolia-style search system powered by Typesense and Laravel Scout for fast, responsive wiki content search.

## Features

### 🔍 **Global Header Search**
- **Keyboard shortcut**: `Cmd/Ctrl + K` to open search anywhere
- **Instant search**: Real-time search with 300ms debounce
- **Highlighted results**: Search terms highlighted in titles and content
- **Keyboard navigation**: Arrow keys to navigate, Enter to select, Esc to close
- **Smart suggestions**: Shows relevant pages with match scoring

### 📊 **Search Capabilities**
- **Full-text search**: Searches both page titles and content
- **Namespace organization**: Results organized by wiki namespaces
- **Smart highlighting**: Visual emphasis on matching terms
- **Recent content priority**: Recently modified pages ranked higher

## Technical Implementation

### Backend Components

1. **WikiPage Model** (`app/Models/WikiPage.php`)
   - Eloquent model with Scout searchable trait
   - Handles DokuWiki content parsing and indexing

2. **WikiContentService** (`app/Services/WikiContentService.php`)
   - Syncs content from DokuWiki to local database
   - Smart change detection for efficient updates

3. **ScoutTypesenseEngine** (`app/Services/ScoutTypesenseEngine.php`)
   - Custom Scout engine for Typesense integration
   - Handles search indexing and retrieval

4. **WikiSearchController** (`app/Http/Controllers/WikiSearchController.php`)
   - API endpoints for search and suggestions

### Frontend Components

1. **WikiSearch.vue** (`resources/js/components/WikiSearch.vue`)
   - Algolia-style search modal component
   - Keyboard shortcuts and navigation
   - Real-time search with debouncing

2. **AppHeader.vue** (Updated)
   - Integrated search accessible from everywhere
   - Mobile and desktop responsive

## Console Commands

```bash
# Full sync of all wiki pages
php artisan wiki:sync-all

# Incremental sync (only updated pages)  
php artisan wiki:sync-updates

# View search statistics
php artisan wiki:stats
```

## Scheduled Tasks

The system automatically syncs updated content every hour via:
```php
Schedule::command('wiki:sync-updates')->hourly()
```

## Environment Configuration

Add these variables to your `.env`:

```env
# Typesense Configuration
TYPESENSE_HOST=localhost
TYPESENSE_PORT=8108
TYPESENSE_PROTOCOL=http
TYPESENSE_API_KEY=your_api_key

# Scout Configuration  
SCOUT_DRIVER=typesense
```

## API Endpoints

- `GET /api/wiki/search?q=query&limit=10` - Main search with highlights
- `GET /api/wiki/suggest?q=query&limit=5` - Autocomplete suggestions

## Usage

1. **Quick Search**: Click the search icon in the header or press `Cmd/Ctrl + K`
2. **Type to search**: Results appear instantly as you type
3. **Navigate**: Use arrow keys to navigate results
4. **Select**: Press Enter or click to open a page
5. **Advanced**: Use "View all results" for comprehensive search page

## Performance

- **Indexed Content**: 122 wiki pages currently indexed
- **Search Speed**: Sub-100ms search responses
- **Update Frequency**: Hourly automatic syncing
- **Smart Caching**: Only updates modified content

## Future Enhancements

- Search filters by namespace
- Search within specific date ranges  
- Advanced search operators
- Search analytics and popular queries
- PDF and attachment search integration