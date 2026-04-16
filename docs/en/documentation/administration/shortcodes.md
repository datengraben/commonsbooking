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

## List of all bookings

List of all bookings, i.e., own bookings of the logged-in user.
Users in the administrator role see all bookings here.

  * Shortcode: `[cb_bookings]`
  * [Users with the cb_manager role](../basics/permission-management) see all their own bookings and bookings of the items and locations assigned to them.
  * Import to digital calendar via [iCalendar](../manage-bookings/icalendar-feed) format possible

![](/img/shortcode-cb-bookings.png)

## Support link

Renders a link to the CommonsBooking funding page on betterplace.org, naming CommonsBooking and wielebenwir e.V. Place this on any page of your site to let visitors know they can support the plugin's development.

  * Shortcode: `[cb_support_link]`
  * Parameters:
    * `text`: The visible link label. Defaults to `Support CommonsBooking & wielebenwir e.V.`
    * `class`: One or more additional CSS classes added to the `<a>` element, useful for styling the link as a button.

**Default usage:**
```
[cb_support_link]
```

**Custom label:**
```
[cb_support_link text="We support CommonsBooking!"]
```

**With a CSS class (e.g. for button styling):**
```
[cb_support_link text="Support us" class="my-button"]
```

The rendered link always opens in a new tab and points to the betterplace.org donation page for wielebenwir e.V., the non-profit organisation behind CommonsBooking.
