### TODO:
- **DONE** Every Product should have two prices [Wholesale, Retail].
- **DONE** add is_retail_active and is_wholesale_active to product table.
- **DONE** if a user wrote the wholesale TRY Price automatically, USD Price will be calculated and the opposite is the same.
- **DONE** Categories CURD.
- **DONE** sections-component:
    - **DONE** in the edit modal in the search product field, it should be rested when the modal is closed.
    - **DONE** in the edit modal in the product table when use reorder the product X to order 1 now all the products should be reordered.
    - **DONE** make an enum for the section positions.
- **DONE** Discuss: should we create two section types ? retail and wholesale?
- **DONE** Ordering System.
- **DONE** Settings.
- **DONE** Analytics.
- **DONE** Add products to the cart
- **DONE** Shopping Cart:
 - **DONE** When to Sync Cart Data.
    - ###DONE### User Logs In:
        - Why: To merge guest cart (from localStorage) into the user’s stored cart.
        - What to Do:
            - If local cart has items → sync them to DB.
            - Merge with DB cart if exists (handle duplicates or quantity summing).
            - Remove retail items if user is shop_owner or salesperson.
    - ###DONE User Proceeds to Checkout:
        - Why: To ensure the cart is stored in DB and validated (e.g., stock check).
        - What to Do: Push latest cart from localStorage to DB before checkout begins.
    - ###DONE  User Navigates Away (Optional)
        - Why: A safety net to persist cart data before the tab closes or navigates.
        - How: Use window.addEventListener('beforeunload').
    - ###DONE When User Logs Out
        - Why: To clear or reset sync flags (syncedUserId) to avoid stale data on next login.
        - What to Do: Clear sync flags and optionally clear cart (based on use case).
    - ###DONE When Logged-In User Changes Device
        - Why: User opens site on a new device; need to load their cart from DB.
        - What to Do: Pull cart from DB and push it into localStorage.
- **DONE** Update the analytics id.


- **TODO** Dashboard:
    - **TODO** a page to view all notifications.
    - **TODO** Users Manager.
    - **TODO** when product is deleted, it should be removed from the analytics (Most Viewed Products).
- **TODO** new user registration process
- **TODO** Users Manager.
- **TODO** Implements the new font type to the frontend.
- **TODO** Translate the website to Turkish.

###BUGS:
- **DONE**: You can not clear the cart if user not logged in.




###Customer_Trust_Features:
- Live chat supported with ai or n8n maybe the idea is not clear yet but it sounds amazing to gain customer trust.
- low-risk ordering: 
  - don't limit the order quantity (for customers who have proven that they are shop owners or salespersons)
  - if the quantity is higher than x, the customer can order it without registration.
- add social proof early—photos of successful deliveries, real shop owners giving feedback, WhatsApp support, etc. (not clear yet how to implement this ask chatgpt).


###Marketing via WhatsApp, Instagram, TikTok:
-  just telling people “we launched” isn’t enough. You need to answer: Why should they care?

### Payment Methods:
- Start with COD + WhatsApp confirmation of order.

### Plan for the next 2 weeks:
- Define the offer: Write a simple pitch like this:
  - "İndirimGo – Türkiye’nin en uygun toptan telefon aksesuar platformu. Kapıda ödeme, hızlı teslimat, dükkanına özel fiyatlar."
  - You can use this everywhere—Facebook groups, WhatsApp, even ad captions




### AI Agent
- set up digitalocean ai agent and teach him how to negotiate with customers about the prices.


### Test Group:
- Pick 5–10 shop owners you know
- Let them place orders manually through WhatsApp using your website. 
- Deliver personally or via cargo to collect feedback.


### Marketing Strategy:
- Create 2–3 Strong Marketing Posts and Ask your friends to repost them. Share in 5–10 relevant Facebook groups.
- “Kapıda Ödeme ile En Ucuz Toptan Aksesuarlar!”
  “Kargo Bedava! İlk Siparişe %10 İndirim”
  “Yeni Ürünler Eklendi – Hemen Göz At!”


### List expected delivery time, return policy, and contact info clearly.



##Our Job now is not to build a big company. It's to get 5-10 shops to order twice from you. If they do, you have a real business.
