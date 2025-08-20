# Wiki Frontend Application

A modern, mobile-responsive frontend for DokuWiki using Laravel, Inertia.js, Vue 3, and identity provider authentication.

## Features

- 🔐 **Identity Provider Authentication** - Secure login using OpenID Connect
- 📱 **Mobile-Responsive Design** - Optimized for viewing on mobile devices
- 🔍 **Advanced Search** - Search through wiki pages and content
- 📄 **Wiki Page Viewing** - Clean, readable interface for wiki content
- 🔗 **JSON-RPC Integration** - Connect to existing DokuWiki installations
- ⚡ **Fast & Modern** - Built with Vue 3, Tailwind CSS, and modern web technologies

## Requirements

- PHP 8.2 or higher
- Composer
- Node.js 18+ and npm
- SQLite (default) or MySQL/PostgreSQL
- A DokuWiki instance with JSON-RPC API enabled
- Identity provider with OpenID Connect support

## Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd wiki-new-app
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies**
   ```bash
   npm install
   ```

4. **Environment Configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure your environment variables**
   
   Edit `.env` with your specific configuration:

   ```env
   # Identity Provider Settings
   IDENTITY_OPENID_CONFIGURATION=https://your-idp.com/.well-known/openid_configuration
   IDENTITY_CLIENT_ID=your-client-id
   IDENTITY_CLIENT_SECRET=your-client-secret
   IDENTITY_CALLBACK_URL=http://localhost:8000/auth/callback

   # Wiki Access Control (comma-separated group IDs)
   WIKI_ALLOWED_GROUP_IDS=group-id-1,group-id-2

   # DokuWiki API Configuration
   DOKUWIKI_URI=https://your-dokuwiki.com
   DOKUWIKI_USERNAME=api_user
   DOKUWIKI_PASSWORD=api_password
   ```

6. **Run migrations**
   ```bash
   php artisan migrate
   ```

7. **Build frontend assets**
   ```bash
   npm run build
   ```

## Development

For development with hot reloading:

```bash
# Terminal 1: Start Laravel development server
php artisan serve

# Terminal 2: Start Vite development server
npm run dev
```

## DokuWiki Configuration

### Enable JSON-RPC API

1. In your DokuWiki `conf/local.php`, ensure:
   ```php
   $conf['remote'] = 1;
   $conf['remoteuser'] = 'your-api-user';
   ```

2. Create an API user account in DokuWiki with appropriate permissions

3. Test the API endpoint:
   ```
   curl -X POST https://your-dokuwiki.com/lib/exe/xmlrpc.php \
     -H "Content-Type: application/json" \
     -u "username:password" \
     -d '{"jsonrpc":"2.0","id":"1","method":"wiki.getVersion","params":[]}'
   ```

## Identity Provider Setup

### Required Scopes
- `openid` (required)
- `profile` (for user name)
- `email` (for user email)
- `groups` (for access control)

### Callback URL
Configure your identity provider with the callback URL:
```
https://your-wiki-app.com/auth/callback
```

## Access Control

Users must be members of specified groups to access the wiki. Configure allowed group IDs in the `WIKI_ALLOWED_GROUP_IDS` environment variable:

```env
WIKI_ALLOWED_GROUP_IDS=staff-group-id,admin-group-id
```

## Available Routes

- `/` - Welcome page
- `/login` - Login page (redirects to identity provider)
- `/` - Wiki home page (requires authentication)
- `/wiki/` - Wiki index
- `/wiki/search` - Search pages
- `/wiki/{page}` - View specific wiki page
- `/wiki/{page}/history` - View page history
- `/wiki/attachments` - Browse attachments

## API Integration

The application integrates with DokuWiki using these JSON-RPC methods:

- `wiki.getAllPages()` - Get all pages
- `wiki.getPage(page)` - Get page content
- `wiki.getPageInfo(page)` - Get page metadata
- `wiki.htmlPage(page)` - Get rendered HTML
- `wiki.search(query)` - Search pages
- `wiki.getRecentChanges()` - Get recent changes
- `wiki.getBackLinks(page)` - Get page backlinks
- `wiki.getAttachments()` - Get attachments

## Mobile Optimization

The interface is specifically designed for mobile viewing with:

- Responsive layout that adapts to screen size
- Touch-friendly navigation
- Optimized typography for readability
- Fast loading and minimal data usage
- Offline-capable progressive web app features

## Security

- Authentication through trusted identity provider
- Group-based access control
- Secure session management
- CSRF protection
- XSS protection through Vue.js

## Troubleshooting

### Common Issues

1. **Identity provider authentication fails**
   - Verify OpenID configuration URL is accessible
   - Check client ID and secret
   - Ensure callback URL is correctly registered

2. **DokuWiki API connection fails**
   - Verify DokuWiki URL and credentials
   - Check that JSON-RPC is enabled in DokuWiki
   - Test API connection manually

3. **Access denied after login**
   - Check user group membership
   - Verify `WIKI_ALLOWED_GROUP_IDS` configuration

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).