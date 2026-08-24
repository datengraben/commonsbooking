# Shortcodes for frontend display

You can display CommonsBooking content (e.g., automatically generated item lists) on the website using shortcodes. Shortcodes can be inserted into any WordPress page. [Official WordPress documentation](https://en.support.wordpress.com/shortcodes).

The display of a shortcode can be influenced by certain parameters.

Example parameters:

  * `orderby`: Determines the attribute by which to sort, e.g., `orderby=post_title` for sorting by the name of a post.
  * `order`: Determines the sort order. Ascending `ASC` and descending `DESC`.

These parameters are valid for the following shortcodes available through the CommonsBooking plugin:

## Item list

Displays a list of all published items with the locations where they are located.

  * Shortcode: `[cb_items]`
  * Parameters:
    * `category_slug`: Category filter
    * `p`: Display only a single item, where 1234 is the numeric ID of the item.
      ```
      [cb_items p=1234]
      ```
    * `location-id`: Display only items from one location, where 1234 is the numeric ID of the location post.
      ```
      [cb_items location-id=1234]
      ```

![](/img/shortcode-cb-items.png)

**Display only a specific category?**

If you have assigned categories to items, you can display only items of a specific category via a parameter. To do this, first find the slug of the category via the category menu and then use it as follows.

Example:
```
[cb_items category_slug=slug]
```

## Single item

Displays a single item in list view (see above).

* Shortcode: `[cb_items]`
* Parameters: `p` the post ID of your item

Example:
```
[cb_items p=1234]
```

## Map with filter option

Displays a map of all published items.
A map must first be set up under "CommonsBooking -> Maps". [More about setting up and configuring maps](./map-embed).

  * Shortcode: `[cb_map]`
  * Parameters (**required!**): `id`

![](/img/shortcode-cb-map.png)

## Map with item list

::: tip Since version 2.9
:::

Previously, each shortcode could only be used independently, meaning a filter applied on the map had no effect on the adjacent item list. For this purpose, there is now the new shortcode

  * Shortcode: `[cb_search]`
  * Parameters (**required!**): `id`

![](/img/shortcode-cb-search-map.png)

[Additional parameters and detailed documentation](./new-frontend)

## Item table with availability

Displays a table of all published items with the locations where they are located and their current availability.

  * Shortcode: `[cb_items_table]`
  * Parameters:
    * `days`: The number of days to display is set to 31 by default. This value can be adjusted using the days attribute. Example to display only 10 days.

      Example:
      ```
      [cb_items_table days=10]
      ```
    * `desc`: Additionally, a brief description can be inserted above the table using the desc attribute.

      Example:
      ```
      [cb_items_table desc=Cargo bikes]
      ```
    * `itemcat`: Filter by item categories

      Example:
      ```
      [cb_items_table itemcat=itemcategoryslug]
      ```
    * `locationcat`: Filter by location categories

      Example:
      ```
      [cb_items_table locationcat=locationcategoryslug]
      ```

![](/img/shortcode-cb-items-table.png)

## Location list

Displays a list of all published locations with the items that are located there

  * Shortcode: `[cb_locations]`

![](/img/shortcode-cb-locations.png)

## Nearby locations or items

Displays a carousel of the nearest locations or items relative to a reference point. Distances are computed from the geo coordinates of the locations and shown (rounded to whole kilometers) on each card. If nothing is within the maximum distance, a short text message is shown instead.

  * Shortcode: `[cb_nearby]`

When placed on an item or location detail page, the shortcode inherits the current post's coordinates automatically (a location uses its own coordinates, an item those of its bookable locations).

### Parameters

  * `type` – `locations` (default) or `items`: what to list.
  * `max_distance` – maximum distance in kilometers. Objects farther away are not shown.
  * `max_results` – maximum number of cards shown.
  * `visible` – number of cards shown side by side on wide screens (the carousel moves through the rest).
  * `post_id` – the reference post whose coordinates are used (defaults to the current post).
  * `lat` / `lon` – explicit reference coordinates, e.g. `[cb_nearby lat=50.94 lon=6.95]`.
  * `lat_meta` / `lon_meta` – names of meta fields on the current post to read the coordinates from. Useful for dynamic subpages that store a location in custom meta fields, e.g. `[cb_nearby lat_meta=my_lat lon_meta=my_lon]`.

Examples:

```
[cb_nearby type=items max_distance=10]
[cb_nearby type=locations lat=50.94 lon=6.95 max_distance=25 visible=2]
[cb_nearby lat_meta=event_lat lon_meta=event_lon]
```

### Global activation

Under **Settings → Templates → Nearby locations & items** the carousel can be enabled to appear automatically below every item and/or location detail page, with a configurable type, maximum distance, result count and number of visible cards.

A parameter set directly on the shortcode takes precedence over the global configuration. If **"Global configuration overrides shortcode parameters"** is enabled, the global settings win instead.

## List of all bookings

List of all bookings, i.e., own bookings of the logged-in user.
Users in the administrator role see all bookings here.

  * Shortcode: `[cb_bookings]`
  * [Users with the cb_manager role](../basics/permission-management) see all their own bookings and bookings of the items and locations assigned to them.
  * Import to digital calendar via [iCalendar](../manage-bookings/icalendar-feed) format possible

![](/img/shortcode-cb-bookings.png)
