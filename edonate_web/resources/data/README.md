# Barangay map centers

`philippines_barangay_centers_2019.json` is a compact local index generated from the low-resolution 2019 barangay TopoJSON files in [faeldon/philippines-json-maps](https://github.com/faeldon/philippines-json-maps/tree/master/2019/topojson/barangays/lowres).

Each JSON row is `[PSGC barangay code, barangay, municipality/city, province, latitude, longitude]`. Coordinates are polygon centroids computed from the decoded boundaries; a bounding-box center is used only when a polygon has no usable area. The map aggregates by the registered barangay and never uses donor home coordinates.

The upstream boundaries are a 2019 snapshot and may not contain every later administrative change. A location is left in the admin “Unmapped completed donors” list when the dataset has no unique matching barangay in the same municipality and province.

To regenerate the index, provide the upstream repository's `2019/topojson/barangays/lowres` directory as the first argument:

```sh
npm run build:barangay-centers -- "path/to/barangays/lowres"
```

The included dataset is distributed under the upstream MIT license; see `philippines-json-maps-LICENSE.txt`.
