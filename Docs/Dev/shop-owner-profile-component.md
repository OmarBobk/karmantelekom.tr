# Shop Owner Profile Component

## Overview

The `ShopOwnerProfile` component is a comprehensive dashboard for shop owners to view and manage their business information. It provides a clean, modern interface with sidebar navigation and card-based design.

## Features

### 1. Sidebar Navigation
- **Basic Information**: Shop details, contact information, Google Maps integration, and document management
- **Business Details**: Business information and social media links
- **Order History**: Recent orders and order statistics
- **Products & Preferences**: Top performing products
- **Notifications**: Notification settings and preferences

### 2. Key Metrics Dashboard
- Total Revenue
- Pending Orders
- Total Products (from orders)
- Average Order Value

### 3. Enhanced Basic Information Section
- **Shop Details**: Name, address, phone, owner information
- **Account Information**: User email display with clickable mailto link (from user account)
- **Google Maps Integration**: Interactive map showing shop location
- **Social Media Links**: Display and management of social media profiles
- **Document Management**: Upload and view tax ID documents and business licenses
- **Edit Functionality**: Modal form for updating all shop information
- **Password Management**: Secure password change functionality with validation

### 4. Responsive Design
- Mobile-friendly layout
- Clean card-based design
- Modern UI with Tailwind CSS
- Smooth transitions and hover effects

## Access

### Route
```
GET /shop/profile
```

### Middleware
- `auth`: User must be authenticated
- `shop.creation`: Shop creation middleware

### Authorization
- Only users with `shop_owner` role can access
- Users must have an associated shop

## Navigation

The component is accessible through the user menu in the frontend header:
- For shop owners: "Shop Profile" link appears in the auth dropdown
- For admin/salesperson: "Dashboard" link appears (redirects to backend)

## Data Sources

### Metrics
- **Total Orders**: Count of all orders for the shop
- **Total Revenue**: Sum of all order totals
- **Pending Orders**: Orders with 'pending' status
- **Completed Orders**: Orders with 'delivered' or 'completed' status
- **Average Order Value**: Average of all order totals
- **Total Products**: Distinct products from order items

### Shop Information
- **Basic Details**: Name, address, phone from shops table
- **Owner Email**: Email from user account (shop owner)
- **Social Media**: JSON array of platform and URL pairs
- **Documents**: File paths for tax documents and business licenses
- **Google Maps**: Integration using shop address for location display

### Recent Orders
- Last 5 orders with customer information
- Order details including total price and date

### Top Products
- Products ordered by total quantity sold
- Based on order items from the shop's orders

## Error Handling

- Graceful handling of missing data
- Error logging for debugging
- User-friendly error messages
- Fallback to empty states when data is unavailable

## Styling

The component uses:
- Tailwind CSS for styling
- Rounded corners and shadows for modern look
- Gradient backgrounds for visual appeal
- Consistent spacing and typography
- Hover effects and transitions

## Form Features

### Edit Modal
- **Shop Information**: Name, address, phone fields
- **Social Media Management**: Add/remove social media links dynamically
- **Document Upload**: Tax ID and business license file uploads
- **Validation**: Client-side and server-side validation
- **File Handling**: Support for PDF, JPG, JPEG, PNG formats (max 2MB)
- **Real-time Updates**: Livewire-powered form with instant feedback

### Password Change Modal
- **Current Password Verification**: Validates existing password before allowing changes
- **New Password Requirements**: Minimum 8 characters with confirmation
- **Password Confirmation**: Ensures new password is entered correctly twice
- **Security Best Practices**: Uses Laravel's Hash facade for secure password hashing
- **Validation**: Comprehensive validation with user-friendly error messages
- **Success Feedback**: Clear success notifications after password change

## Future Enhancements

Potential improvements:
- Real-time order updates
- Export functionality for reports
- Integration with notification system
- Product management features
- Advanced document management with preview
- Business hours and availability settings
