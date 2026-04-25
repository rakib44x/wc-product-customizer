=== WC Product Customizer ===
Contributors: rakib44x
Author URI: https://bd.linkedin.com/in/rirakeeb
Tags: woocommerce, product customizer, personalization, design, fabric.js, t-shirt, business card, canvas
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let customers personalize WooCommerce products from the frontend with a Canva-style editor (text, colors, fonts, drag & drop). Admin receives print-ready PNG + PDF on every order.

== Description ==

WC Product Customizer turns any WooCommerce product into a personalizable product — business cards, t-shirts, mugs, invitations, anything. You configure the base design (one or multiple sides, e.g. Front / Back) and mark the editable regions right on the image. Customers then open a clean Fabric.js-powered editor on the product page where they can:

* Type into editable text regions (with placeholder text)
* Change text font, size, color, bold / italic / underline, alignment
* Add new text objects anywhere by dragging
* Recolor color-fill regions marked by the admin
* Change the background color and opacity of each side
* Switch between sides (Front / Back / etc.) at any time

On "Save & Continue", the editor exports each side as a high-resolution PNG and the plugin bundles them into a print-ready PDF. The order line item carries links to every PNG and the combined PDF so the store owner can send directly to print.

== Features ==

* Admin panel integrated into WooCommerce **Product data → Customizer** tab
* Per-product toggle for "Customizable"
* Unlimited sides — each with its own base image and editable regions
* Three region types: **Editable Text**, **Color Fill**, **Image** (future)
* Draw rectangular regions by dragging on the base image preview
* Configurable DPI and print size (inches / cm / mm)
* Frontend Canva-style editor built on Fabric.js 5
* Live thumbnails of the saved design in cart, checkout, order admin and order emails
* High-resolution PNG per side + combined PDF written to `uploads/wcpc-designs/<id>/`
* HPOS-compatible (declares WooCommerce custom-order-tables support)
* Zero external dependencies

== Installation ==

1. Upload the `wc-product-customizer` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. Edit any WooCommerce product, open the **Customizer** tab in the Product data box, tick **Enable Customizer**, add sides, upload base images, draw your editable regions and press **Update**.
4. Visit the product on the frontend and click **Customize Design**.

== Frequently Asked Questions ==

= Does this work with WooCommerce HPOS (custom order tables)? =

Yes. The plugin declares compatibility and stores per-line-item meta, which is the portable way for both legacy and HPOS storage.

= Where do the design files live? =

Inside `wp-content/uploads/wcpc-designs/<design_id>/` — one PNG per side, a combined `design.pdf`, plus a `manifest.json` describing the design.

= Do I need Composer or any external service? =

No. The plugin bundles Fabric.js and ships a dependency-free PDF generator that embeds JPEG-transcoded copies of each side.

== Changelog ==

= 1.0.0 =
* Initial release.
