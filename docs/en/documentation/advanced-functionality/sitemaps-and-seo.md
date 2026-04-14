# Sitemaps and SEO

So that **Items** and **Locations** can be discovered by search engines and listed in your site's
sitemap, a small amount of configuration is recommended. CommonsBooking handles the technical
groundwork automatically — you just need to choose how to expose it.

---

## What CommonsBooking does automatically

CommonsBooking registers its custom post types with the correct WordPress flags so that the right
content is discoverable and the right content stays private:

| Post type | Public | In sitemap by default |
|---|---|---|
| Item (`cb_item`) | Yes | **Yes** |
| Location (`cb_location`) | Yes | **Yes** |
| Timeframe (`cb_timeframe`) | No | No |
| Booking (`cb_booking`) | No | No |
| Restriction (`cb_restriction`) | — | **Excluded by plugin** |
| Map (`cb_map`) | — | **Excluded by plugin** |

`cb_restriction` and `cb_map` are excluded from the sitemap via the `wp_sitemaps_post_types`
filter even though they are technically public post types (required for front-end rendering). They
contain administrative configuration, not content that should be indexed.

---

## Recommended setup: install an SEO plugin

WordPress 5.5 includes a built-in sitemap at `/wp-sitemap.xml`. For most sites, installing a
dedicated SEO plugin gives you better control over titles, descriptions, canonical URLs, and
structured data. We recommend one of the following:

### Yoast SEO

[Yoast SEO](https://yoast.com/wordpress/plugins/seo/) is the most widely used SEO plugin for
WordPress. After installation:

1. Go to **Yoast SEO → Search Appearance → Content Types**.
2. Find **Items** (`cb_item`) and **Locations** (`cb_location`).
3. Set **Show in search results** to **Yes** for both.
4. Optionally customise the SEO title and meta description templates using Yoast's variables
   (e.g. `%%title%% – %%sitename%%`).
5. Yoast will automatically generate a sitemap entry for each published Item and Location.

### RankMath

[RankMath](https://rankmath.com/) is a lightweight alternative with a guided setup wizard. After
installation:

1. Go to **RankMath → Titles & Meta → Items** (and repeat for **Locations**).
2. Enable **Add to sitemap**.
3. Set a sensible title pattern, e.g. `%title% - %sitename%`.
4. RankMath will include Items and Locations in its sitemap automatically.

---

## Using the WordPress core sitemap (no SEO plugin)

If you prefer not to install an SEO plugin, the core sitemap at `/wp-sitemap.xml` already
includes Items and Locations out of the box. Individual post type sitemaps are available at:

```
/wp-sitemap-posts-cb_item-1.xml
/wp-sitemap-posts-cb_location-1.xml
```

You can submit these URLs directly to Google Search Console or Bing Webmaster Tools.

---

## Excluding specific posts from the sitemap

To exclude individual Items or Locations from the sitemap (e.g. items in draft or under
maintenance), set the post status to **Draft** rather than **Published**. Both the core sitemap
and SEO plugins only index `publish`-status posts.

With Yoast SEO you can also exclude a single post by opening its editor and setting
**Yoast SEO → Advanced → Allow search engines to show this post in search results** to **No**.

---

## Structured data (JSON-LD)

For richer search results (e.g. showing the item name and availability directly in Google),
structured data markup is needed. This is beyond CommonsBooking's scope — a developer can add
`wp_head` hooks with custom JSON-LD using the post meta fields CommonsBooking stores (location
address, item description, etc.). See the
[hooks and filters reference](hooks-and-filters) for available data access points.
