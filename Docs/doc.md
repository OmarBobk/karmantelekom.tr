Prompt:
Build a professional and user-friendly Sections Management Page for my Laravel 11 dashboard. This page will allow me to
add, edit, and delete sections, as well as manage the products attached to each section. The design should reflect the
same slider-style layout as seen in the "New Arrivals" section on the customers' interface. Follow these requirements:

Requirements:

1. Page Layout:
   Section Display Table:
   Display all sections in a slider-style card layout, similar to how "New Arrivals" is displayed on the customer
   interface.
   Each card should include:
   Section Name (e.g., "New Arrivals").
   Position (e.g., "Slider").
   Order (e.g., "1").
   List of attached products (use product thumbnails for a visual overview).
   Action buttons: Edit, Delete, and Manage Products.
2. Managing Sections:
   Add Section:

Create a button labeled "Add Section" that opens a modal for adding a new section.
Modal fields:
Section Name: Text input.
Position: Dropdown (e.g., "Slider").
Order: Numeric input.
On submission:
Save the new section to the database.
Display a success message using Tailwind CSS components.
Edit Section:

Clicking the Edit button on a section card opens a modal pre-filled with the section's details for editing.
On submission:
Update the section in the database.
Refresh the section display.
Delete Section:

Clicking the Delete button shows a confirmation modal.
On confirmation:
Delete the section from the database.
Remove it from the displayed list.

3. Managing Products in Sections:
   Clicking the Manage Products button on a section card opens a new modal or page that:
   Lists all products currently attached to the section in a table format.
   Includes a Search/Add Product feature:
   Search existing products by name, ID, or other criteria.
   Add products to the section using a multi-select dropdown or checkboxes.
   Includes a Remove Product button next to each product in the list to detach it from the section.
4. Data Relationships:
   Use a many-to-many relationship:
   Table: sections (id, name, position, order, timestamps).
   Table: products (id, name, price, image, timestamps).
   Pivot table: section_products (id, section_id, product_id).
5. Dynamic and Responsive:
   Use Livewire 3 to dynamically handle data loading, updates, and real-time validation.
   Ensure the page is fully responsive and styled with Tailwind CSS for a clean and modern design.
6. Bonus Features (Optional):
   Allow drag-and-drop reordering of sections (updates the order field in the database).
   Add a preview mode for each section to simulate how it will appear on the customer interface.
   Include pagination or lazy loading for sections with many products.
   Enable sorting and filtering of products within each section.
   Reference Design:
   Use the provided image as inspiration for how the section cards and sliders should look. Maintain a consistent visual
   design with the customer interface.

Deliverables:
A fully functional Sections Management Page integrated into the Laravel 11 admin dashboard.
Modular, clean, and well-documented code using Laravel best practices.
Dynamic and intuitive UI built with Livewire and Tailwind CSS.
Robust backend functionality for managing sections and their attached products.
