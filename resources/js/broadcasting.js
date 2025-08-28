import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Initialize Echo
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
});

// Broadcasting Event Handlers
class BroadcastingManager {
    constructor() {
        this.userId = window.userId || null;
        this.userRole = window.userRole || null;
        this.initializeListeners();
    }

    initializeListeners() {
        this.listenToShopEvents();
        this.listenToOrderEvents();
        this.listenToNotifications();
        this.listenToUserEvents();
    }

    // Shop Events
    listenToShopEvents() {
        // Public shop channel
        window.Echo.channel('shops')
            .listen('.shop.created', (e) => {
                this.handleShopCreated(e);
            })
            .listen('.shop.assigned', (e) => {
                this.handleShopAssigned(e);
            });

        // Admin dashboard channel
        if (this.userRole === 'admin') {
            window.Echo.private('admin.dashboard')
                .listen('.shop.created', (e) => {
                    this.handleShopCreated(e);
                })
                .listen('.shop.assigned', (e) => {
                    this.handleShopAssigned(e);
                });
        }

        // Salesperson-specific channel
        if (this.userId) {
            window.Echo.private(`salesperson.${this.userId}`)
                .listen('.shop.assigned', (e) => {
                    this.handleShopAssigned(e);
                });
        }
    }

    // Order Events
    listenToOrderEvents() {
        // Public orders channel
        window.Echo.channel('orders')
            .listen('.order.created', (e) => {
                this.handleOrderCreated(e);
            })
            .listen('.order.updated', (e) => {
                this.handleOrderUpdated(e);
            });

        // Admin dashboard channel
        if (this.userRole === 'admin') {
            window.Echo.private('admin.dashboard')
                .listen('.order.created', (e) => {
                    this.handleOrderCreated(e);
                })
                .listen('.order.updated', (e) => {
                    this.handleOrderUpdated(e);
                });
        }
    }

    // Notifications
    listenToNotifications() {
        if (this.userId) {
            window.Echo.private(`App.Models.User.${this.userId}`)
                .notification((notification) => {
                    this.handleNotification(notification);
                });
        }
    }

    // User Events
    listenToUserEvents() {
        if (this.userId) {
            window.Echo.private(`App.Models.User.${this.userId}`)
                .listen('.user.updated', (e) => {
                    this.handleUserUpdated(e);
                });
        }
    }

    // Event Handlers
    handleShopCreated(data) {
        console.log('Shop Created:', data);
        
        // Show notification
        this.showNotification({
            title: 'New Shop Created',
            message: `Shop "${data.shop.name}" has been created`,
            type: 'success',
            icon: 'shop',
        });

        // Update UI if on shops page
        this.updateShopsList(data.shop);
        
        // Dispatch custom event for Livewire components (both backend and frontend)
        window.dispatchEvent(new CustomEvent('shop-created', { detail: data }));
        
        // Update notification count for frontend
        this.updateFrontendNotificationCount();
    }

    handleShopAssigned(data) {
        console.log('Shop Assigned:', data);
        
        const assignmentType = data.assignment_type === 'reassignment' ? 'reassigned' : 'assigned';
        
        // Show notification
        this.showNotification({
            title: 'Shop Assignment',
            message: `Shop "${data.shop.name}" has been ${assignmentType} to ${data.salesperson.name}`,
            type: 'info',
            icon: 'user-check',
        });

        // Update UI if on shops page
        this.updateShopAssignment(data);
        
        // Dispatch custom event for Livewire components (both backend and frontend)
        window.dispatchEvent(new CustomEvent('shop-assigned', { detail: data }));
        
        // Update notification count for frontend
        this.updateFrontendNotificationCount();
    }

    handleOrderCreated(data) {
        console.log('Order Created:', data);
        
        // Show notification
        this.showNotification({
            title: 'New Order',
            message: `Order #${data.order.id} created with status: ${data.order.status_label}`,
            type: 'success',
            icon: 'shopping-cart',
        });

        // Update UI if on orders page
        this.updateOrdersList(data.order);
        
        // Dispatch custom event for Livewire components (both backend and frontend)
        window.dispatchEvent(new CustomEvent('order-created', { detail: data }));
        
        // Update notification count for frontend
        this.updateFrontendNotificationCount();
    }

    handleOrderUpdated(data) {
        console.log('Order Updated:', data);
        
        // Show notification
        this.showNotification({
            title: 'Order Updated',
            message: `Order #${data.order.id} has been updated`,
            type: 'info',
            icon: 'edit',
        });

        // Update UI if on orders page
        this.updateOrderStatus(data);
        
        // Dispatch custom event for Livewire components (both backend and frontend)
        window.dispatchEvent(new CustomEvent('order-updated', { detail: data }));
        
        // Update notification count for frontend
        this.updateFrontendNotificationCount();
    }

    handleNotification(notification) {
        console.log('Notification received:', notification);
        
        // Show notification
        this.showNotification({
            title: 'New Notification',
            message: notification.description,
            type: 'info',
            icon: 'bell',
            action: notification.action_url ? {
                url: notification.action_url,
                text: notification.action_text || 'View Details'
            } : null,
        });

        // Update notification count
        this.updateNotificationCount();
        
        // Dispatch custom event for Livewire components (both backend and frontend)
        window.dispatchEvent(new CustomEvent('notification-received', { detail: notification }));
        
        // Update notification count for frontend
        this.updateFrontendNotificationCount();
        
        // Also dispatch the specific Echo event for Livewire components that need it
        const userId = this.userId;
        if (userId) {
            window.dispatchEvent(new CustomEvent(`echo-private:App.Models.User.${userId},.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated`, { 
                detail: notification 
            }));
        }
    }

    handleUserUpdated(data) {
        console.log('User Updated:', data);
        
        // Update user profile if needed
        this.updateUserProfile(data);
        
        // Dispatch custom event for Livewire components
        window.dispatchEvent(new CustomEvent('user-updated', { detail: data }));
    }

    // UI Update Methods
    showNotification({ title, message, type = 'info', icon = 'info', action = null }) {
        // Check if we have a notification system (like Toastr, SweetAlert, etc.)
        if (window.toastr) {
            window.toastr[type](message, title);
        } else if (window.Swal) {
            window.Swal.fire({
                title,
                text: message,
                icon: type,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
            });
        } else {
            // Fallback to browser notification
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(title, { body: message, icon: '/favicon.ico' });
            } else {
                // Simple alert fallback
                console.log(`${title}: ${message}`);
            }
        }

        // Add to notification list if exists
        this.addToNotificationList({ title, message, type, icon, action });
    }

    updateShopsList(shop) {
        // Update shops list if on shops page
        const shopsList = document.querySelector('[data-shops-list]');
        if (shopsList) {
            // Trigger Livewire refresh or add new shop to list
            window.Livewire.find(shopsList.__livewire.id).call('refreshShops');
        }
    }

    updateShopAssignment(data) {
        // Update shop assignment display
        const shopElement = document.querySelector(`[data-shop-id="${data.shop.id}"]`);
        if (shopElement) {
            // Update salesperson info
            const salespersonElement = shopElement.querySelector('[data-salesperson]');
            if (salespersonElement) {
                salespersonElement.textContent = data.salesperson.name;
            }
        }
    }

    updateOrdersList(order) {
        // Update orders list if on orders page
        const ordersList = document.querySelector('[data-orders-list]');
        if (ordersList) {
            // Trigger Livewire refresh or add new order to list
            window.Livewire.find(ordersList.__livewire.id).call('refreshOrders');
        }
    }

    updateOrderStatus(data) {
        // Update order status display
        const orderElement = document.querySelector(`[data-order-id="${data.order.id}"]`);
        if (orderElement) {
            // Update status
            const statusElement = orderElement.querySelector('[data-order-status]');
            if (statusElement) {
                statusElement.textContent = data.order.status_label;
                statusElement.className = `status-${data.order.status}`;
            }
        }
    }

    updateNotificationCount() {
        // Update notification badge count
        const notificationBadge = document.querySelector('[data-notification-count]');
        if (notificationBadge) {
            const currentCount = parseInt(notificationBadge.textContent) || 0;
            notificationBadge.textContent = currentCount + 1;
        }
    }

    updateFrontendNotificationCount() {
        // Update frontend notification count specifically
        const frontendNotificationBadge = document.querySelector('.notification-bell [data-notification-count]');
        if (frontendNotificationBadge) {
            const currentCount = parseInt(frontendNotificationBadge.textContent) || 0;
            frontendNotificationBadge.textContent = currentCount + 1;
        }
        
        // Also trigger Livewire refresh for frontend components
        const frontendNotificationBell = document.querySelector('[wire\\:id*="notification-bell"]');
        if (frontendNotificationBell && window.Livewire) {
            const component = window.Livewire.find(frontendNotificationBell.__livewire.id);
            if (component) {
                component.call('refreshNotifications');
            }
        }
    }

    addToNotificationList(notification) {
        // Add to notification dropdown/list
        const notificationList = document.querySelector('[data-notifications-list]');
        if (notificationList) {
            // Add new notification to the list
            const notificationItem = this.createNotificationItem(notification);
            notificationList.insertBefore(notificationItem, notificationList.firstChild);
        }
    }

    createNotificationItem(notification) {
        const item = document.createElement('div');
        item.className = `notification-item notification-${notification.type}`;
        item.innerHTML = `
            <div class="flex items-center p-3">
                <div class="flex-shrink-0">
                    <i class="fas fa-${notification.icon} text-${notification.type === 'success' ? 'green' : 'blue'}-500"></i>
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-medium text-gray-900">${notification.title}</p>
                    <p class="text-sm text-gray-500">${notification.message}</p>
                </div>
                ${notification.action ? `
                    <div class="ml-3">
                        <a href="${notification.action.url}" class="text-sm text-blue-600 hover:text-blue-500">
                            ${notification.action.text}
                        </a>
                    </div>
                ` : ''}
            </div>
        `;
        return item;
    }

    updateUserProfile(data) {
        // Update user profile information if needed
        const profileElements = document.querySelectorAll('[data-user-profile]');
        profileElements.forEach(element => {
            // Update profile information
            if (data.name) {
                const nameElement = element.querySelector('[data-user-name]');
                if (nameElement) nameElement.textContent = data.name;
            }
        });
    }
}

// Initialize broadcasting when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Set user information from meta tags or data attributes
    window.userId = document.querySelector('meta[name="user-id"]')?.content;
    window.userRole = document.querySelector('meta[name="user-role"]')?.content;
    
    // Initialize broadcasting manager
    window.broadcastingManager = new BroadcastingManager();
});

// Export for use in other modules
export default BroadcastingManager;
