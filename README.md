# Social Media Posting Clone System
*This is Outdated*
A professional social media platform clone built with HTML, CSS, JavaScript, PHP, and AJAX. Features beautiful 3D CSS effects, Bootstrap integration, and backend-ready PHP files for database integration.

## Features

### ✅ User Authentication Flow
- Login page with school logo support
- Session management
- Remember me functionality

### ✅ Home Feed Flow
- Profile section with stats
- Timeline with posts
- Create post section

### ✅ Create Post Flow
- Text content input
- Media upload (images)
- Real-time preview

### ✅ Validation
- File size validation (max 5MB)
- File format validation (PNG/JPG only)
- SweetAlert error messages

### ✅ Save Post Flow
- Backend-ready PHP endpoint
- Database integration ready
- File upload handling

### ✅ Display Post Flow
- Timeline display
- User name and photo
- Like, Comment, Share buttons
- Real-time updates

### ✅ Interaction Flow
- Like/Unlike posts
- Add comments
- Share posts
- Update counts in real-time

### ✅ Edit Post Flow
- Edit post content
- Update in database
- Real-time UI update

### ✅ Delete Post Flow
- Confirmation dialog
- Remove from database
- Update timeline

### ✅ Notification Flow
- Notification panel
- Real-time notifications
- Unread badge

### ✅ Admin Moderation
- Reports management
- Remove posts
- Activity logs

## File Structure

```
├── index.html              # Login page
├── home.html               # Home feed page
├── assets/
│   ├── css/
│   │   └── style.css      # Main stylesheet with 3D effects
│   ├── js/
│   │   ├── login.js        # Login functionality
│   │   └── home.js         # Home page functionality
│   └── images/
│       └── default-avatar.png  # Default avatar image
├── api/
│   ├── login.php           # Login endpoint
│   ├── create_post.php     # Create post endpoint
│   ├── get_posts.php       # Get posts endpoint
│   ├── like_post.php       # Like/unlike endpoint
│   ├── add_comment.php     # Add comment endpoint
│   ├── share_post.php      # Share post endpoint
│   ├── edit_post.php       # Edit post endpoint
│   ├── delete_post.php     # Delete post endpoint
│   ├── get_notifications.php  # Get notifications endpoint
│   ├── get_reports.php     # Get reports (admin)
│   ├── get_logs.php        # Get logs (admin)
│   └── remove_post.php     # Remove post (admin)
├── imagesrc/
│   └── logo.jpg            # School logo (add your logo here)
└── uploads/
    └── posts/              # Uploaded post images
```

## Setup Instructions

1. **Add School Logo**
   - Place your school logo at `imagesrc/logo.jpg`
   - Supported formats: JPG, PNG
   - Recommended size: 120x120px or larger

2. **Configure PHP**
   - Ensure PHP is installed and running
   - PHP version 7.4 or higher recommended
   - Enable file uploads in `php.ini`

3. **Database Integration**
   - All PHP files in `api/` folder are backend-ready
   - Follow the TODO comments in each PHP file
   - Replace demo responses with actual database queries
   - Database schema examples are provided in comments

4. **File Uploads**
   - Create `uploads/posts/` directory
   - Set permissions: `chmod 755 uploads/posts/`
   - Ensure PHP has write permissions

5. **Web Server**
   - Use Apache, Nginx, or PHP built-in server
   - For PHP built-in server: `php -S localhost:8000`
   - Access at `http://localhost:8000`

## Database Schema (Suggested)

```sql
-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100),
    avatar VARCHAR(255),
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Posts table
CREATE TABLE posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    content TEXT,
    media VARCHAR(255),
    likes_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    shares_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Likes table
CREATE TABLE likes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_like (post_id, user_id)
);

-- Comments table
CREATE TABLE comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Shares table
CREATE TABLE shares (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50),
    message TEXT,
    post_id INT,
    read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Reports table
CREATE TABLE reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    reporter_id INT NOT NULL,
    reason TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (post_id) REFERENCES posts(id),
    FOREIGN KEY (reporter_id) REFERENCES users(id)
);

-- Admin logs table
CREATE TABLE admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action VARCHAR(100),
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id)
);
```

## Demo Mode

The system currently runs in demo mode for front-end testing:
- Login accepts any credentials
- Posts are stored in browser localStorage
- All interactions work without database

To enable database mode:
1. Set up database connection in each PHP file
2. Remove demo responses
3. Uncomment database queries

## Technologies Used

- **HTML5** - Structure
- **CSS3** - Styling with 3D effects
- **JavaScript (ES6+)** - Interactivity
- **Bootstrap 5.3** - Responsive framework
- **PHP 7.4+** - Backend API
- **AJAX** - Asynchronous requests
- **SweetAlert2** - Beautiful alerts
- **Font Awesome** - Icons

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Notes

- All PHP files include CORS headers for cross-origin requests
- File uploads are validated for size (5MB max) and type (PNG/JPG only)
- SweetAlert2 is used for all user confirmations and alerts
- The system is fully responsive and works on mobile devices
- Admin features are hidden unless user has admin privileges

## License

This project is created for educational purposes.

## Support

For database integration help, refer to the TODO comments in each PHP file. All database queries are documented with examples.
