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

A ticket on an order line with a £0 total, in a processing or completed order, counts as a free entry. Free entries are excluded from paid tickets (paid tickets = purchased ticket count minus free entries) and bring no revenue. A 100% coupon also gives a £0 line, so tickets bought with one count as free entries too. This rule is provisional and lives in one method, so it can change in one place.

== Changelog ==

= 0.1.0 =
* Plugin skeleton and shared PHP/JS calculation engine.
