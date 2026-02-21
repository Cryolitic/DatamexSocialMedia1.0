# Quick Setup Guide

## Step 1: Add Your School Logo
1. Place your school logo file at: `imagesrc/logo.jpg`
2. Supported formats: JPG, PNG
3. Recommended size: 120x120px or larger

## Step 2: Start the Server

### Option A: PHP Built-in Server
```bash
php -S localhost:8000
```

### Option B: XAMPP/WAMP
1. Copy project to `htdocs` or `www` folder
2. Start Apache server
3. Access at `http://localhost/system-capstone`

### Option C: Live Server (VS Code Extension)
1. Right-click on `index.html`
2. Select "Open with Live Server"

## Step 3: Test the System

1. **Login Page**: Open `index.html`
   - Enter any username and password (demo mode)
   - For admin access, use username: `admin`

2. **Home Feed**: After login, you'll see the home feed
   - Create a post with text and/or image
   - Test like, comment, and share features
   - Edit and delete your own posts

3. **Notifications**: Click the bell icon to see notifications

4. **Admin Panel**: If logged in as admin, access admin features from the dropdown menu

## Step 4: Database Integration (For Your Backend Team)

All PHP files in the `api/` folder are ready for database integration:

1. **Database Connection**: Add your database credentials in each PHP file
2. **Uncomment Database Queries**: Remove demo responses and uncomment database code
3. **Create Tables**: Use the SQL schema provided in `README.md`

### Example Database Connection:
```php
$db = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');
```

## File Permissions

Ensure these directories are writable:
- `uploads/posts/` - For uploaded images

On Linux/Mac:
```bash
chmod 755 uploads/posts
```

On Windows: Right-click folder → Properties → Security → Edit permissions

## Troubleshooting

### Images not loading?
- Check file paths are correct
- Ensure `imagesrc/logo.jpg` exists
- Check browser console for errors

### File upload not working?
- Check PHP `upload_max_filesize` in `php.ini`
- Ensure `uploads/posts/` directory exists and is writable
- Check file permissions

### API errors?
- Check browser console (F12) for AJAX errors
- Verify PHP is running
- Check PHP error logs

## Demo Mode

Currently, the system runs in **demo mode**:
- Login accepts any credentials
- Posts stored in browser localStorage
- All features work without database

To enable database mode, integrate database connections in PHP files.

## Support

For detailed information, see `README.md`
