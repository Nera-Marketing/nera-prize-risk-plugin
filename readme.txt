=== Nera – Prize Risk ===
Contributors: nera
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later

Prize cost, break-even and live exposure figures for Lottery for WooCommerce competitions.

== Description ==

Adds prize cost tracking to Lottery for WooCommerce (Giveaway for WooCommerce) competitions and works out, from the same maths everywhere (product edit screen, report, calculator):

* Net ticket price = ticket price x (1 - payment fee)
* Total cost = prize cost + other costs
* Break-even tickets = ceil(total cost / net ticket price), and as a share of all tickets
* Net revenue = order line totals (processing + completed, minus refunds) x (1 - payment fee)
* Position = net revenue - total cost
* Sell-through = (paid tickets + free entries) / total tickets
* Sold-out result = net revenue + (tickets left x net ticket price) - total cost

The net ticket price is never rounded before use; figures are rounded only for display.

Admin only: the plugin loads nothing on the front end. It requires WooCommerce and Lottery for WooCommerce, and is compatible with WooCommerce HPOS.

= Free (postal) entries =

A ticket counts as a free entry when its order (processing or completed) is tagged "Postal entry" or its order line has a £0 total. Staff tick the "Postal entry" box on the edit-order screen; orders created in wp-admin are ticked automatically on save when they have at least one lottery line and every lottery line is £0 (other lines don't count; a manual untick is kept), and tagged orders show "Postal" in the orders list. Tagged orders add no revenue, even with a price. Free entries are excluded from paid tickets (paid tickets = purchased ticket count minus free entries) and bring no revenue. A 100% coupon also gives a £0 line, so tickets bought with one count as free entries too. The rule lives in the data class in two places, both through `is_postal_line()`: `count_free_entries()` (free tickets) and the postal-line skip in `get_figures()` (revenue).

== Changelog ==

= 0.1.0 =
* New - Plugin skeleton and shared PHP/JS calculation engine, matching the brief's worked example exactly.
* New - Prize Risk tab on lottery products: prize cost and other costs fields, with a break-even line that updates while typing.
* New - Prize Risk admin menu for administrators and shop managers, with a payment fee % setting and a what-if calculator box.
* New - Competitions table with live sold, free and paid tickets, revenue, position and break-even from LTY and order data (HPOS), cached per product and cleared on order and product changes.
* New - Status, category and month filters, and a CSV export of the filtered rows with formula-safe text cells.
* New - Category and month rollups, and an Items view that groups competitions by title.
