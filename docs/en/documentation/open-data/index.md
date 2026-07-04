# Open Data & Interoperability

CommonsBooking powers a large and growing number of non-commercial cargo-bike-sharing and tool-lending
initiatives across Europe. For associations, researchers, policymakers and industry bodies interested in
shared mobility, the plugin doesn't just run bookings locally — it also publishes that activity through
open, standards-based interfaces, so it can be aggregated, analyzed and integrated into wider
mobility-data ecosystems.

This page summarizes the interfaces relevant to that kind of work. For technical/setup details, follow the
links into the [Extensions / API](../api/) documentation.

## GBFS (General Bikeshare Feed Specification)

Since version 2.5, CommonsBooking can publish location, item and real-time availability data using
[GBFS](https://www.gbfs.org/documentation/), the open standard used by bike-share systems, MaaS apps and
city/regional mobility-data platforms to ingest shared-vehicle fleets. This means community-run cargo-bike
fleets can, in principle, show up alongside commercial bike-share systems in the same data pipelines.

→ [GBFS documentation](../api/gbfs)

## The Commons-API

The Commons-API is a purpose-built open schema for connecting individual CommonsBooking installations to
central, cross-organization platforms — for example nationwide directories of available cargo bikes. It
exposes standardized data about lending organizations (projects), locations, items and their availability.

→ [What is the CommonsAPI?](../api/what-is-the-commonsapi)
→ [Using the CommonsBooking API](../api/commonsbooking-api)

## Scale and community context

CommonsBooking originated with the "Free Cargo Bikes" initiatives in Cologne, Germany, and today underpins
the umbrella association [Verband Freie Lastenräder e.V.](https://freies-lastenrad.org/verband/) (VFL),
which lists more than 100 free cargo-bike initiatives. See the [About](../../about/) page for background.

## Get in touch

If your organization works on shared-mobility data standards, cargo-bike advocacy, or research, and you'd
like to talk about integrating with or building on this data, please reach out via our
[contact page](../../contact).
