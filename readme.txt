=== Sales by State Report for Event Tickets ===
Contributors: BusinessBloomer
Donate link: https://salesbystate.com/
Tags: sales-report, sales-by-state, event-tickets, tickets, analytics
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See a yearly breakdown of Event Tickets sales by state / county / province for a given country, filterable by order status.

== Description ==

Sales by State Report for Event Tickets adds a report showing Tickets Commerce sales grouped by state, county or province, for a chosen year and a chosen set of order statuses.

It appears under **Tickets → Sales by State**.

It answers the question territory planning actually asks: how much did each state buy in a given year, counting only the orders that matter.

This plugin is an Event Tickets extension. It requires [Event Tickets](https://wordpress.org/plugins/event-tickets/) to be installed and active, and it reports on Tickets Commerce orders.

Documentation: [salesbystate.com](https://salesbystate.com/)

= What the report shows =

* Sales for every state in the selected country
* A summary of that amount across all states
* Sortable columns and paginated results
* States with no sales, shown as zero rather than hidden

= Filters =

* **Country** — United States, Canada, and the United Kingdom. Defaults to the United States.
* **Year** — a rolling list that starts ten years back and gains a year each January without dropping one. Defaults to the current year.
* **Order status** — a checkbox list of Tickets Commerce order statuses. Defaults to Completed (`tec-tc-completed`).

= How the figures are calculated =

The figure is the order total Tickets Commerce stores on the order (`_tec_tc_order_total_value`). Tickets Commerce does not calculate tax, shipping, or IRS withholding, so there is no separate net vs gross column.

Refunds are not modelled as separate records. An order that has been refunded is controlled by the status filter.

= Performance =

Sales for a whole year are answered by one indexed query that returns one row per state. The response size does not grow with the number of orders.

= Data and privacy =

The plugin creates one custom database table holding, per order: the order ID, order status, creation and paid dates, billing country and state codes, currency, and the order, tax, shipping and net totals. Tax and shipping are stored as zero. It stores no names, addresses, email addresses or any other personal data.

Nothing is sent anywhere. The plugin makes no external HTTP requests, includes no third-party services, and collects no analytics or telemetry.

Deleting the plugin removes the table and its options.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/sales-by-state-report-for-event-tickets`, or install it through the Plugins screen.
2. Activate the plugin. Event Tickets must already be installed and active.
3. Go to **Tickets → Sales by State**.

On a site that already has Tickets Commerce orders, those orders are read into the report table once. This starts on its own when you open the report. If it has not finished, a progress bar shows how far along it is.

== Frequently Asked Questions ==

= The report shows zeros but I have orders. =

Your existing orders are still being read into the report table. Open the report and the progress bar will show how far along it is. It continues on its own; you can leave the page.

If only Completed is selected, tick any other statuses that should count.

= Which address does it group by? =

The billing country and state stored on the Tickets Commerce order. Tickets Commerce does not have a shipping address.

= Are refunds deducted? =

The status filter decides whether an order counts. Refunded orders are excluded unless you tick Refunded.

= Which date does the year filter use? =

The date the order was paid (Completed, Refunded, and Reversed), falling back to the date it was created.

= Why is there no Net vs Gross column? =

Tickets Commerce does not calculate sales tax or shipping. The reported figure is the order total.

= Can I change the default order status? =

Yes, with the `sbset_default_statuses` filter.

= Where can I get support? =

Use the [WordPress.org support forum](https://wordpress.org/support/plugin/sales-by-state-report-for-event-tickets/) for this plugin.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
