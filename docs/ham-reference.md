# HAM Reference (HypeApps Markup)

> **For backend authors writing HAM templates served by Laravel.**
>
> This is the canonical reference for the HAM dialect — every tag, attribute,
> style class, action, control, and reactive variable supported by the
> hypeapps framework that the MoviePlz mobile shell renders. The framework
> itself lives in another repo; this doc is what you read when you're
> writing markup that the mobile app will display.
>
> Mirror of `hypeapps/docs/REFERENCE.md` — the source-of-truth in the
> framework repo. If something here disagrees with the framework, the
> framework wins. Re-copy the file when the framework updates.
>
> **Authoring rules specific to MoviePlz** (Laravel/Blade integration,
> file conventions, etc.) are in `CLAUDE.md` at the project root.

---

## Table of Contents

- [Core Concepts](#core-concepts)
- [Markdown](#markdown)
- [Tags](#tags)
- [Styles](#styles)
- [Controls (htmx-like)](#controls)
- [Reactivity (Alpine-like)](#reactivity)
- [Icons](#icons)
- [Colors](#colors)
- [Configuration](#configuration)
- [Appendix: Aliases](#appendix-aliases)

---

## Core Concepts

### Three Modes of Interaction

1. **Hypermedia Partials (htmx-style)** - Fetch HTML, swap into target
   ```html
   <button action="/api/content" target="#result" swap="inner">Load</button>
   ```

2. **Navigation (Flutter-style)** - Push new screen onto Navigator
   ```html
   <navigate action="file:/profile.html" title="Profile">Go</navigate>
   ```

3. **Front-end Reactivity (Alpine-style)** - Local state, no server
   ```html
   <box var-count="0">
     <button @click="$count = $count + 1">+</button>
     <text @text="$count"></text>
   </box>
   ```

### Data Flow

```
HTML File → Parse → TagData → Flutter Widgets → HypeWidget wrapper → Render
```

---

## Markdown

HypeApps supports basic Markdown syntax within text content. Markdown is automatically converted to HTML tags before parsing.

### Supported Syntax

| Markdown | HTML | Description |
|----------|------|-------------|
| `**text**` | `<b>text</b>` | Bold text |
| `*text*` | `<i>text</i>` | Italic text |
| `_text_` | `<i>text</i>` | Italic text (alternative) |
| `` `code` `` | `<code>code</code>` | Inline code |
| `[text](url)` | `<a href="url">text</a>` | Link |

### Examples

```html
<p>This is **bold** and this is *italic* text.</p>
<p>Use `code` for inline code snippets.</p>
<p>Visit [our website](https://example.com) for more info.</p>
```

Renders as:

```html
<p>This is <b>bold</b> and this is <i>italic</i> text.</p>
<p>Use <code>code</code> for inline code snippets.</p>
<p>Visit <a href="https://example.com">our website</a> for more info.</p>
```

### Links

Links support both external and internal URLs:

| URL Format | Behavior |
|------------|----------|
| `https://...` | Opens in external browser |
| `http://...` | Opens in external browser |
| `file:/...` | Internal navigation (planned) |
| `/path` | Internal navigation (planned) |

```html
<!-- External link - opens in browser -->
<p>Check out [Flutter](https://flutter.dev)!</p>

<!-- Using HTML directly also works -->
<a href="https://flutter.dev">Flutter Website</a>
```

### Code

Inline code is rendered with:
- Monospace font
- Subtle gray background
- Slightly smaller font size
- Pink/magenta text color

```html
<p>Use the `print()` function to debug.</p>
```

### Notes

- Markdown is processed **before** HTML parsing
- Markdown inside HTML tag attributes is **not** processed
- For complex formatting, use HTML tags directly
- Nested markdown (e.g., `***bold italic***`) is not supported

---

## Tags

### Compound Tags

#### `<app>`
Root container. Creates MaterialApp.

Children: `<config>`, `<screen>`

```html
<app>
  <config primary_color="blue"
          google_fonts="Lato,Oswald:700"
          font_urls="Brand=https://cdn.co/brand.ttf"
          cache_max_age="30"
          cache_max_size="524288000"
          show_ham="longpress">
  </config>
  <screen>...</screen>
</app>
```

#### `<screen>`
Full screen (Scaffold). Contains slots for app structure.

| Attribute | Description |
|-----------|-------------|
| `bg_color` | Background color |
| `style` | Style classes (including bg-img-*) |
| `selected` | Initially selected tab/nav index (default: 0) |
| `expand` | Expand tab bar content in parent scroll ("true"/"false") |

Children slots: `<app-bar>`, `<drawer-left>`, `<drawer-right>`, `<main>`, `<bottom-navigation-bar>`

#### `<config>`
App configuration. Not rendered.

| Attribute | Description |
|-----------|-------------|
| `app_key` | App identifier |
| `primary_color` | Primary theme color |
| `bg_color` | Default background |
| `default_text_color` | Default text color |
| `selected_color` | Selected item color |
| `unselected_color` | Unselected item color |
| `google_fonts` | Google Fonts to load (e.g., "Lato,Merriweather,Oswald:700,Roboto+Slab:400;700") |
| `font_urls` | Direct font URLs: "FamilyName=https://url/font.ttf,Brand=https://cdn/brand.woff2" |
| `font_cache_max_size` | Font cache max size in bytes |
| `show_ham` | HAM peek mode: "longpress" to show HAM source on long press |
| `performance` | Performance monitoring: "overlay" (Flutter graphs), "timing" (frame logs), "all" (both) |
| `cache_max_age` | Image cache max age in days (default: 90) |
| `cache_max_size` | Image cache max total size in bytes (default: 1073741824) |
| `cache_medium_width` | Medium cache variant width in px (default: 400) |
| `cache_small_width` | Small cache variant width in px (default: 150) |
| `cache_cleanup_interval` | Cache cleanup interval in minutes (default: 60) |

---

### App Structure Tags

#### `<app-bar>`
Top app bar.

| Attribute | Description |
|-----------|-------------|
| `title` | Title text |
| `leading_icon` | Left icon name |

#### `<drawer-left>` / `<drawer>`
Left navigation drawer.

Children: `<drawer-header>`, `<list-tile>`

#### `<drawer-header>`
Header area inside drawer.

#### `<list-tile>`
List item in drawer. Renders as ListTile.

#### `<main>`
Primary content area. Usually has `id="main"` for targeting.

| Attribute | Description |
|-----------|-------------|
| `id` | Element ID (typically "main") |

#### `<bottom-navigation-bar>`
Bottom navigation container.

| Attribute | Description |
|-----------|-------------|
| `selected_color` | Active item color |
| `unselected_color` | Inactive item color |
| `background_color` / `bg_color` | Bar background |
| `height` | Bar height (default: 60) |
| `padding` | Padding "v,h" (default: "6,8") |
| `font_size` | Label font size (default: 12) |
| `font_weight` | bold, normal |
| `icon_size` | Icon size (default: 24) |
| `ripple_size` | Tap ripple size (default: 90) |
| `alignment` | expanded (default), center, start, end |

Children: `<bottom-nav-item>`

#### `<bottom-nav-item>`
Navigation bar item.

| Attribute | Description |
|-----------|-------------|
| `label` | Text label |
| `icon` | Icon name |
| `action` | Action URL (file:, folder:, http) |
| `loading` | Indicator type while loading |
| `selected` | Is selected ("true"/"false") |

---

### Layout Tags

#### `<section>`, `<container>`
Block containers. Render children in Column. Use `<box>` for general-purpose containers.

#### `<box>`
General-purpose container. Most common element. Supports all control attributes.

#### `<center>`
Centers children horizontally and vertically.

#### `<stack>`
Overlays children (Flutter Stack).

#### `<flex>`
Multi-column layout using Table.

| Attribute | Description |
|-----------|-------------|
| `columns` | Number of columns |
| `column_widths` | Space-separated flex weights (e.g., "1 2 1") |

```html
<flex column_widths="1 3">
  <box>Sidebar</box>
  <box>Main</box>
</flex>
```

#### `<hr>`
Horizontal rule / divider.

#### `<inline-content>`
Wraps children inline (Wrap widget).

| Attribute | Description |
|-----------|-------------|
| `spacing` | "h,v" or single value |
| `horizontal-space` | Horizontal spacing |
| `vertical-space` | Vertical spacing |

---

### Text Tags

#### `<h1>` - `<h6>`
Headings. Sizes: 32, 28, 24, 20, 18, 16px.

#### `<p>`
Paragraph. Block-level text.

#### `<span>`
Inline text container. Renders as Row.

#### `<text>`
Plain text. Useful with `@text` directive.

#### `<b>`, `<strong>`
Bold text.

#### `<i>`, `<em>`
Italic text.

#### `<br>`
Line break (8px spacing).

#### `<code>`
Inline code. Monospace font with gray background.

```html
<p>Use the <code>print()</code> function.</p>
```

#### `<a>`, `<link>`
Anchor/link. Opens external URLs in browser.

| Attribute | Description |
|-----------|-------------|
| `href` | URL to navigate to |

```html
<a href="https://flutter.dev">Flutter Website</a>
<link href="https://example.com">Example</link>
```

---

### Form Tags

#### `<form>`
Form container.

#### `<input>`
Text input. Self-closing.

| Attribute | Description |
|-----------|-------------|
| `type` | text, password, email, number, hidden |
| `placeholder` | Placeholder text |
| `label` | Label text |
| `value` | Initial value |
| `icon` | Prefix icon name |
| `helper` | Helper text below field |

#### `<button>`
Button element.

| Attribute | Description |
|-----------|-------------|
| `variant` | elevated (default), filled, tonal, outlined, text |
| `color` | Button color (hex or named) |
| `icon` | Icon name |
| `text` | Button text (if no children) |

```html
<button variant="filled" color="blue" icon="send">Submit</button>
<button variant="outlined">Cancel</button>
```

#### `<textarea>`
Multi-line input.

| Attribute | Description |
|-----------|-------------|
| `placeholder` | Placeholder text |
| `label` | Label text |
| `value` | Initial value |
| `rows` | Number of rows (default: 3) |
| `icon` | Prefix icon name |
| `helper` | Helper text below field |

#### `<select>`
Dropdown. Children: `<option>`

| Attribute | Description |
|-----------|-------------|
| `placeholder` | Placeholder text (default: "Select an option") |
| `label` | Label text |
| `icon` | Prefix icon name |

#### `<option>`
Select option.

| Attribute | Description |
|-----------|-------------|
| `value` | Option value |
| `selected` | Pre-selected ("true"/"false") |

#### `<label>`
Form label. Medium weight, 14px font.

---

### Material Component Tags

#### `<card>`
Material Card with elevation and rounded corners.

| Attribute | Description |
|-----------|-------------|
| `elevation` | Shadow depth (default: 2) |
| `color` | Background color |
| `radius` | Border radius (default: 12) |

```html
<card elevation="4" radius="16">
  <p style="font-bold">Card Title</p>
  <p style="text-gray-500">Card content</p>
</card>
```

#### `<badge>`
Badge overlay on child element.

| Attribute | Description |
|-----------|-------------|
| `count` | Badge number/text (omit for dot) |
| `color` | Badge color (default: red) |
| `size` | Badge size (default: 18) |

```html
<badge count="3"><icon name="mail blue size-26"></icon></badge>
<badge><icon name="notifications"></icon></badge>
```

#### `<tooltip>`
Material Tooltip wrapping child.

| Attribute | Description |
|-----------|-------------|
| `message` | Tooltip text |

#### `<progress-bar>`
Linear progress indicator. Self-closing.

| Attribute | Description |
|-----------|-------------|
| `value` | 0.0-1.0 (null = indeterminate) |
| `color` | Bar color |
| `background-color` | Track color |
| `height` | Bar height (default: 4) |
| `radius` | Corner radius (default: 2) |

```html
<progress-bar value="0.7" color="blue" height="6"/>
```

#### `<chip>`
Material Chip.

| Attribute | Description |
|-----------|-------------|
| `label` | Chip text |
| `icon` | Avatar icon name |
| `color` | Chip color (hex) |
| `variant` | filled (default), outlined, assist, filter, input, suggestion |

#### `<switch>`
Material Switch. Self-closing.

| Attribute | Description |
|-----------|-------------|
| `checked` | Initial state ("true"/"false") |
| `label` | Label text |
| `color` | Active color (hex) |

#### `<checkbox>`
Material Checkbox. Self-closing.

| Attribute | Description |
|-----------|-------------|
| `checked` | Initial state ("true"/"false") |
| `label` | Label text |
| `color` | Check color (hex) |

#### `<radio-group>` / `<radios>`
Radio button group container. Children: `<radio>`

#### `<radio>`
Radio button. Self-closing.

| Attribute | Description |
|-----------|-------------|
| `value` | Radio value |
| `selected` / `checked` | Is selected ("true"/"false") |
| `label` | Label text |
| `color` | Active color (hex) |

#### `<fab>`
Floating Action Button. Self-closing.

| Attribute | Description |
|-----------|-------------|
| `icon` | Icon name (default: add) |
| `label` | Text label (makes it extended) |
| `color` | Background color (hex) |

#### `<slider>`
Material Slider. Self-closing.

| Attribute | Description |
|-----------|-------------|
| `value` | Current value (default: 50) |
| `min` | Minimum (default: 0) |
| `max` | Maximum (default: 100) |
| `label` | Label above slider |
| `color` | Active color (hex) |

#### `<search-bar>`
Search text field. Self-closing.

| Attribute | Description |
|-----------|-------------|
| `placeholder` | Placeholder text (default: "Search...") |

#### `<expansion-panel>` / `<accordion>`
Expandable panel.

| Attribute | Description |
|-----------|-------------|
| `title` | Panel title |
| `subtitle` | Subtitle text |
| `expanded` | Initial state ("true"/"false") |
| `icon` | Leading icon name |

#### `<segmented-button>`
Material 3 segmented button. Children: `<segment>`

#### `<segment>`
Segment option.

| Attribute | Description |
|-----------|-------------|
| `value` | Segment value |
| `label` | Display text |
| `icon` | Icon name |
| `selected` | Is selected ("true"/"false") |

#### `<stepper>`
Material Stepper. Children: `<step>`

| Attribute | Description |
|-----------|-------------|
| `orientation` | vertical (default), horizontal |

#### `<step>`
Stepper step.

| Attribute | Description |
|-----------|-------------|
| `title` | Step title |
| `subtitle` | Subtitle text |
| `state` | indexed, complete, editing, error, disabled |

#### `<data-table>`
Material DataTable. Children: `<data-column>`, `<data-row>`

```html
<data-table>
  <data-column>Name</data-column>
  <data-column>Age</data-column>
  <data-row>
    <data-cell>Alice</data-cell>
    <data-cell>30</data-cell>
  </data-row>
</data-table>
```

#### `<dialog>`
Alert dialog rendered inline.

| Attribute | Description |
|-----------|-------------|
| `id` | Element ID (for media commands: show/dismiss) |
| `title` | Dialog title |
| `icon` | Title icon name |
| `confirm-label` | Confirm button text (default: OK) |
| `cancel-label` | Cancel button text |

#### `<bottom-sheet>`
Material bottom sheet pinned to bottom.

| Attribute | Description |
|-----------|-------------|
| `id` | Element ID (for media commands: show/dismiss) |
| `title` | Sheet title |
| `drag-handle` | Show drag handle (default: true) |
| `background` | Background color |

#### `<snackbar>`
Snackbar pinned to bottom. Self-closing.

| Attribute | Description |
|-----------|-------------|
| `id` | Element ID (for media commands: show/dismiss) |
| `message` | Snackbar text |
| `action-label` | Action button text |
| `color` | Background color (hex) |
| `close-icon` | Show close icon ("true"/"false") |
| `behavior` | floating (default), fixed |
| `duration` | Auto-dismiss seconds (default: 4) |

#### `<dropdown>`
Material 3 DropdownMenu. Children: `<menu-item>`

| Attribute | Description |
|-----------|-------------|
| `label` | Dropdown label |
| `value` | Initial selection |
| `expanded` | Start expanded ("true"/"false") |

#### `<menu-item>`
Dropdown menu item.

| Attribute | Description |
|-----------|-------------|
| `value` | Item value |
| `icon` | Icon name |

---

### Tab & Rail Navigation Tags

#### `<tab-bar>`
Material TabBar with TabBarView content panels.

| Attribute | Description |
|-----------|-------------|
| `indicator-color` | Tab indicator color |
| `label-color` | Active tab label color |
| `unselected-label-color` | Inactive tab label color |
| `background-color` | Tab bar background |
| `height` | Total widget height (px) |
| `scrollable` | Scrollable tabs ("true"/"false") |
| `expand` | No inner scroll — tab content flows in parent scroll ("true"/"false") |

Children: `<tab>`

```html
<tab-bar indicator-color="blue" label-color="blue">
  <tab label="First">
    <p>First tab content</p>
  </tab>
  <tab label="Second" icon="star">
    <p>Second tab content</p>
  </tab>
  <tab label="Lazy" action="file:/content.ham" trigger="click">
    <p>Placeholder until loaded</p>
  </tab>
</tab-bar>
```

#### `<tab>`
Tab panel inside `<tab-bar>`.

| Attribute | Description |
|-----------|-------------|
| `label` | Tab header text |
| `icon` | Tab header icon name |
| `action` | URL for lazy loading |
| `method` | HTTP method (default: GET) |
| `source` | Source type |
| `swap` | inner (default), outer |

#### `<navigation-rail>`
Material NavigationRail with content panels.

| Attribute | Description |
|-----------|-------------|
| `label-type` | all (default), selected, none |
| `color` | Selected indicator color |
| `background-color` | Rail background |
| `extended` | Extended mode ("true"/"false") |
| `height` | Total widget height (px) |
| `selected` | Initially selected index (default: 0) |

Children: `<rail-item>`

```html
<navigation-rail color="indigo" label-type="all">
  <rail-item icon="home" label="Home">
    <p>Home content</p>
  </rail-item>
  <rail-item icon="settings" label="Settings" action="file:/settings.ham" trigger="click">
    <p>Placeholder</p>
  </rail-item>
</navigation-rail>
```

#### `<rail-item>`
Rail destination inside `<navigation-rail>`.

| Attribute | Description |
|-----------|-------------|
| `icon` | Destination icon name |
| `label` | Destination label text |
| `action` | URL for lazy loading |
| `method` | HTTP method (default: GET) |
| `source` | Source type |
| `swap` | inner (default), outer |
| `trigger` | click (default), load, revealed |

---

### Navigation Tags

#### `<navigate>`
Triggers full-page navigation (Flutter Navigator push).

| Attribute | Description |
|-----------|-------------|
| `action` | URL or command (file:, back, root, http) |
| `title` | Page title |
| `navigation` / `stack` | push (default), replace |

```html
<navigate action="file:/settings.html" title="Settings">Go</navigate>
<navigate action="back">Back</navigate>
<navigate action="root">Home</navigate>
<navigate action="file:/page.html" navigation="replace">Replace</navigate>
```

**Note:** `<link>` and `<a>` are documented as aliases but NOT YET IMPLEMENTED.

---

### Special Tags

#### `<icon>`
Material Symbols icon.

| Attribute | Description |
|-----------|-------------|
| `name` / `icon` | Icon name with modifiers |
| `size` | Override size |
| `color` | Override color |
| `style` | Additional style classes |

```html
<icon name="favorite rounded 700 size-lg red fill"></icon>
```

#### `<indicator>`
Loading indicator.

| Attribute | Description |
|-----------|-------------|
| `type` | circular, linear, refresh, cupertino, text, content |
| `color` | Indicator color |
| `show` | Force show ("true") |

#### `<sticky>`
Sticky header that pins at the top of its scrollable parent. Must be inside `<main>` or works automatically in partials via `<root>`.

| Attribute | Description |
|-----------|-------------|
| `min-height` | Minimum header height (default: 50) |
| `max-height` | Maximum header height (default: 50) |
| `mode` | replace (default) or stack |

Supports hierarchical nesting — nested `<sticky>` children become sub-headers with push-off animation.

```html
<!-- Flat sticky -->
<sticky min-height="44" max-height="44">
  <box style="bg-blue p-10"><p style="text-white font-bold">Section</p></box>
</sticky>
<p>Content below sticky header</p>

<!-- Hierarchical sticky -->
<sticky min-height="44" max-height="44">
  <box style="bg-blue p-10"><p style="text-white">Section</p></box>
  <sticky min-height="32" max-height="32">
    <box style="bg-blue-100 p-6"><p style="font-xs">Sub A</p></box>
  </sticky>
  <p>Sub A content</p>
  <sticky min-height="32" max-height="32">
    <box style="bg-blue-100 p-6"><p style="font-xs">Sub B</p></box>
  </sticky>
  <p>Sub B content</p>
</sticky>
```

#### `<include>`
Include content from another file. Processed at parse time.

| Attribute | Description |
|-----------|-------------|
| `file` | File path to include |

```html
<include file="/components/header.html"></include>
```

#### `<date-picker>`
Date picker that navigates to URL with selected date.

| Attribute | Description |
|-----------|-------------|
| `url` | URL template (* replaced with YYYY-MM-DD) |

#### `<globals>`
Global variables container. Not rendered.

#### `<root>`
Root wrapper for partial content loaded via actions. Rendered as a simple Column container. Used internally when fetched HAM fragments need a parent node.

---

### Media Tags

#### `<img>` / `<image>`
Image element. Self-closing.

| Attribute | Description |
|-----------|-------------|
| `src` | Image URL (file:/, https://, asset path, blob:); supports `$variable` interpolation |
| `width` | Width in logical px |
| `height` | Height in logical px |
| `alt` | Semantic accessibility label |
| `fit` | BoxFit: cover (default), contain, fill, fitWidth, fitHeight, none |
| `loading` | fade (default for network), spinner, text:{msg}, image:{path}, none (default for assets) |

```html
<img src="file:/images/photo.jpg" width="200" height="150" fit="cover"/>
<image src="https://example.com/logo.png" width="100"/>
```

#### `<video>`
Video player.

| Attribute | Description |
|-----------|-------------|
| `src` | Video URL (file:/, https://) |
| `id` | Element ID (required for media commands) |
| `autoplay` | Auto-play on load ("true"/"false", default: false) |
| `loop` | Loop playback ("true"/"false", default: false) |
| `muted` | Start muted ("true"/"false", default: false) |
| `controls` | Show player controls ("true"/"false", default: true) |
| `fit` | BoxFit mode (default: contain) |

```html
<video src="file:/media/demo.mp4" id="v1" controls="true" autoplay="true" muted="true"/>
```

#### `<audio>`
Audio player.

| Attribute | Description |
|-----------|-------------|
| `src` | Audio URL (file:/, https://) |
| `id` | Element ID (required for media commands) |
| `autoplay` | Auto-play on load ("true"/"false", default: false) |
| `loop` | Loop playback ("true"/"false", default: false) |
| `muted` | Start muted ("true"/"false", default: false) |
| `controls` | Show player bar ("true"/"false", default: true) |
| `title` | Track title displayed above controls |

```html
<audio src="https://example.com/podcast.mp3" id="a1" controls="true"/>
```

#### Media Commands

Control media playback via `action` attribute using `#id.command` syntax:

| Command | Description |
|---------|-------------|
| `#id.play` | Start playback |
| `#id.pause` | Pause playback |
| `#id.toggle` | Toggle play/pause |
| `#id.stop` | Stop and reset |
| `#id.mute` | Toggle mute |
| `#id.seek:N` | Seek to N seconds |
| `#id.speed:N` | Set playback speed |
| `#id.volume:N` | Set volume (0.0-1.0) |

```html
<button action="#v1.toggle">Play/Pause</button>
<button action="#v1.seek:30">Skip to 30s</button>
```

---

### Platform-Native Tags

#### `<camera>`
Camera preview and capture. Platform-native (iOS/Android only).

| Attribute | Description |
|-----------|-------------|
| `facing` | back (default), front |
| `flash` | auto, on, off (default), torch |
| `resolution` | low, medium, high (default), max |
| `on-capture` | HAM action URL invoked after photo captured |
| `on-error` | HAM action URL invoked on camera error |
| `shutter` | Show capture button (default: true) |
| `preview` | Show live preview (default: true) |
| `overlay` | crosshair, none (default) |
| `loading` | spinner (default), none, text |
| `controls` | Show control bar (default: true) |

#### `<map>`
Interactive map. Children: `<pin>` / `<marker>`

#### `<pin>` / `<marker>`
Map marker inside `<map>`.

#### `<file-picker>`
File picker for selecting files from device.

#### `<markdown>`
Renders markdown content as formatted text.

```html
<markdown># Hello
This is **bold** and *italic*.
- List item 1
- List item 2
</markdown>
```

---

## Styles

Applied via `style` attribute with space-separated classes.

```html
<box style="p-20 bg-blue-100 rounded-lg shadow text-white">...</box>
```

### Spacing

#### Padding
| Class | Description |
|-------|-------------|
| `p-{n}` | All sides |
| `pt-{n}`, `pb-{n}`, `pl-{n}`, `pr-{n}` | Individual sides |
| `px-{n}` | Horizontal |
| `py-{n}` | Vertical |

#### Margin
| Class | Description |
|-------|-------------|
| `m-{n}` | All sides |
| `mt-{n}`, `mb-{n}`, `ml-{n}`, `mr-{n}` | Individual sides |
| `mx-{n}`, `my-{n}` | Horizontal/Vertical |
| `mt-screen-{n/d}` | Top as screen fraction (e.g., mt-screen-1/4) |
| `mb-screen-{n/d}` | Bottom as screen fraction |

### Sizing

#### Width
| Class | Description |
|-------|-------------|
| `w-{n}` | Fixed width (px) |
| `w-{n/d}` | Fraction (e.g., w-1/2) |
| `w-full` | 100% |

#### Height
| Class | Description |
|-------|-------------|
| `h-{n}` | Fixed height (px) |
| `h-{n/d}` | Fraction |
| `h-full`, `h-screen` | Full screen |
| `h-safe` | Safe area (minus insets) |

### Colors

#### Background
| Class | Description |
|-------|-------------|
| `bg-{color}` | Named color |
| `bg-{color}-{100-900}` | With gradation |
| `bg-{r}-{g}-{b}` | RGB |
| `bg-{r}-{g}-{b}-{a}` | RGBA |
| `bg-{hex}`, `bg-#{hex}` | Hex |

#### Text
| Class | Description |
|-------|-------------|
| `text-{color}` | Text color |
| `text-{color}-{100-900}` | With gradation |

### Typography

#### Font Size
| Class | Size |
|-------|------|
| `font-xs`, `text-xs` | 10px |
| `font-sm`, `text-sm` | 12px |
| `font-base`, `text-base` | 14px |
| `font-lg`, `text-lg` | 16px |
| `font-xl`, `text-xl` | 20px |
| `font-2xl`, `text-2xl` | 24px |
| `font-3xl`, `text-3xl` | 30px |
| `font-4xl`, `text-4xl` | 36px |
| `font-size-{n}` | Custom |

#### Font Weight
| Class | Weight |
|-------|--------|
| `font-normal` | 400 |
| `font-bold` | 700 |
| `font-black` | 900 |
| `font-weight-{100-900}` | Specific weight |

#### Font Family
| Class | Font |
|-------|------|
| `font-sans` | System default (resets to platform sans-serif) |
| `font-mono` | JetBrains Mono (monospace) |
| `font-serif` | Playfair Display (serif) |
| `font-handwriting` | Caveat (handwriting) |
| `font-display` | Abril Fatface (display) |
| `font-{family-name}` | Any Google Font — kebab-case to PascalCase (e.g., `font-roboto-slab` → RobotoSlab) |

#### Line Height
| Class | Description |
|-------|-------------|
| `line-height-{n}` | Line height multiplier |

### All Google Fonts (1,923)

See [docs/GOOGLE_FONTS.md](GOOGLE_FONTS.md) for the complete list.


### Borders

#### Width
| Class | Description |
|-------|-------------|
| `border` | 1px all sides |
| `border-{n}` | Custom width all sides |
| `border-t`, `border-b`, `border-l`, `border-r` | 1px single side |
| `border-t-{n}`, `border-b-{n}`, `border-l-{n}`, `border-r-{n}` | Custom single side |

#### Color
| Class | Description |
|-------|-------------|
| `border-{color}` | Border color |

#### Radius
| Class | Radius |
|-------|--------|
| `rounded-xs` | 5px |
| `rounded-sm` | 10px |
| `rounded` | 15px |
| `rounded-lg` | 20px |
| `rounded-xl` | 25px |
| `rounded-full` | Full circle (9999px) |
| `rounded-{n}` | Custom |

### Effects

#### Shadow
| Class | Description |
|-------|-------------|
| `shadow-none` | No shadow |
| `shadow-sm` | Small shadow |
| `shadow` / `shadow-md` | Default shadow |
| `shadow-lg` | Large shadow |
| `shadow-xl` | Extra large shadow |
| `shadow-2xl` | 2x large shadow |
| `shadow-{color}` | Shadow with color (e.g., `shadow-blue`) |
| `shadow-{size}/{opacity}` | Shadow with opacity (e.g., `shadow-lg/50`) |

#### Opacity
| Class | Description |
|-------|-------------|
| `opacity-{0-100}` | Percentage |
| `opacity-{0.0-1.0}` | Decimal |

#### Gradients
| Class | Description |
|-------|-------------|
| `gradient-{start}-{colors}-{end}` | Linear gradient |

Positions: `l` (left), `r` (right), `t` (top), `b` (bottom), `tl` (top-left), `tr` (top-right), `bl` (bottom-left), `br` (bottom-right)

```html
<box style="gradient-l-indigo-blue-r">Left to right</box>
<box style="gradient-tl-purple-pink-br">Diagonal</box>
<box style="gradient-t-indigo-blue-b">Top to bottom</box>
<box style="gradient-l-red-yellow-green-r">Three colors</box>
```

#### Other
| Class | Description |
|-------|-------------|
| `glass` | Frosted glass blur effect |
| `offset-x-{n}`, `offset-y-{n}` | Transform translate (negative: `offset-x--20`) |

#### Press/Interaction Animations
| Class | Description |
|-------|-------------|
| `press:scale-{n}` | Scale on press (e.g., `press:scale-90` = 90%) |
| `press:opacity-{n}` | Opacity on press (e.g., `press:opacity-75`) |
| `press:rotate-{n}` | Rotate on press in degrees (e.g., `press:rotate-5`) |
| `press:elevate-{n}` | Elevate shadow on press (e.g., `press:elevate-8`) |
| `press:color-{color}` | Change color on press (e.g., `press:color-blue`) |
| `transition-{ms}` | Transition duration in ms (e.g., `transition-150`) |

```html
<box style="press:scale-95 press:opacity-80 transition-150">Tap me</box>
```

### Alignment

| Class | Alignment |
|-------|-----------|
| `align-center` | Center |
| `align-left`, `align-l`, `align-cl` | Center Left |
| `align-right`, `align-r`, `align-cr` | Center Right |
| `align-tl`, `align-top-left` | Top Left |
| `align-tr`, `align-top-right` | Top Right |
| `align-bl`, `align-bottom-left` | Bottom Left |
| `align-br`, `align-bottom-right` | Bottom Right |
| `align-tc`, `align-top` | Top Center |
| `align-bc`, `align-bottom` | Bottom Center |

### Background Images

| Class | Description |
|-------|-------------|
| `bg-img-{path}` | Background image |
| `bg-img-fit-{mode}` | cover, contain, fill, fitWidth, fitHeight, none, scaleDown |
| `bg-overlay-{color}` | Color overlay |

### Position Modifiers

| Modifier | Description |
|----------|-------------|
| `first:` | Only apply to first child |
| `last:` | Only apply to last child |
| `skip-first:` | Apply to all except first |
| `skip-last:` | Apply to all except last |

```html
<box style="mt-10 first:mt-0">...</box>
```

### Text Style Inheritance (Cascading)

Text styles cascade from parent to children, similar to CSS. Child elements inherit text properties from their ancestors, and can override specific properties while keeping others.

#### How It Works

Text styles are applied using Flutter's `DefaultTextStyle` mechanism:

1. **Parent styles cascade down** - All text descendants inherit the style
2. **Children can override** - Override only specific properties (color, size, weight)
3. **Unspecified properties are preserved** - If child only sets color, font size from parent is kept

#### Example

```ham
<!-- Outer container sets red color and large font -->
<box style="text-red font-lg">

  <!-- This text is red and large (inherited) -->
  <p>Inherited red and large</p>

  <!-- This text is blue (overridden) but still large (inherited) -->
  <box style="text-blue">
    <p>Blue but still large</p>
  </box>

  <!-- This text is red (inherited) and small (overridden) -->
  <box style="font-sm">
    <p>Red but now small</p>
  </box>

  <!-- This text is green and bold, but still large -->
  <box style="text-green font-bold">
    <p>Green, bold, and still large</p>
  </box>
</box>
```

#### Properties That Cascade

| Property | Classes | Inherits | Can Override |
|----------|---------|----------|--------------|
| Color | `text-{color}` | Yes | Yes |
| Font Size | `font-{size}`, `text-{size}` | Yes | Yes |
| Font Weight | `font-bold`, `font-weight-{n}` | Yes | Yes |
| Line Height | `line-height-{n}` | Yes | Yes |

#### Fast Path Optimization

When a styled container has only a `Text` widget as child and no container properties (bg, padding, margin, border), the style is applied directly to the Text widget instead of wrapping with DefaultTextStyle. This is more efficient.

```ham
<!-- Fast path: style applied directly to Text -->
<box style="text-red font-bold">Just text</box>

<!-- Standard path: DefaultTextStyle wrapper -->
<box style="text-red bg-white p-10">
  <p>Text with container</p>
</box>
```

#### Text Tags

Text formatting tags also participate in the cascade:

| Tag | Adds to Style |
|-----|---------------|
| `<bold>`, `<b>` | `font-weight: bold` |
| `<italic>`, `<i>` | `font-style: italic` |
| `<h1>`-`<h6>` | Bold + specific size |
| `<p>`, `<span>` | No additional style (pure containers) |

```ham
<box style="text-blue">
  <bold>This is bold and blue</bold>
  <italic>This is italic and blue</italic>
</box>
```

#### Combining with Container Styles

Container styles (background, padding, margin, border) don't affect text inheritance:

```ham
<!-- Text style passes through containers with only decoration -->
<box style="text-red font-lg">
  <box style="bg-white p-20 rounded-lg">
    <box style="bg-gray-100 p-10">
      <!-- Still red and large! -->
      <p>Deep nested text</p>
    </box>
  </box>
</box>
```

### Inline Content

| Class | Description |
|-------|-------------|
| `inline-content` | Wrap children inline |
| `inline-content-{h}` | With horizontal spacing |
| `inline-content-{h}-{v}` | With h and v spacing |

---

## Controls

htmx-inspired hypermedia controls. Add to any element.

### action

URL to fetch content from.

| Format | Description |
|--------|-------------|
| `file:/path` | Local asset file |
| `folder:/path` | Folder index |
| `/path` | Relative to base URL |
| `https://...` | Full URL |

### target

Element to swap content into. CSS selector.

| Format | Description |
|--------|-------------|
| `#id` | Target by ID |
| `this` | Target self |
| (none) | Defaults to self or #main |

### swap

How to replace content.

| Value | Description |
|-------|-------------|
| `inner` | Replace children (innerHTML) |
| `outer` | Replace entire element |
| `before` | Insert before target |
| `after` | Insert after target |
| `delete` | Delete target element |
| `none` | Don't swap |

### trigger

Event that initiates action. Default: `click`

| Value | Description |
|-------|-------------|
| `click` / `tap` | On tap/click |
| `doubletap` | On double tap |
| `longpress` | On long press |
| `load` | When mounted |
| `revealed` | When scrolled into view |
| `every {n}s` | Polling interval (e.g., `every 5s`) |
| `swipeleft`, `swiperight`, `swipeup`, `swipedown` | Swipe gestures |
| `drag` | Continuous drag position events |
| `shake` | Device shake (accelerometer) |
| `rotate` | Device rotation (gyroscope) |
| `orientation` | Screen orientation change |
| `change` | Form value changed (switch, checkbox, slider, select, radio, input) |
| `none` | No automatic trigger |

#### Trigger Modifiers

Append `:delay-Xs` or `:delay-Xms` to delay action execution:

```html
<box action="/api/save" trigger="change:delay-500ms">Debounced save</box>
```

### method

HTTP method. Default: `GET`

Values: `GET`, `POST`, `PUT`, `DELETE`, `PATCH`

### indicator

Loading indicator.

| Format | Description |
|--------|-------------|
| `target:#id` | Show in target element |
| `type:circular` | Indicator type |

### action-{trigger} (Multi-Trigger Shorthand)

Attach different actions to different gestures on one element without JSON:

```html
<box action="file:/default.ham"
     action-swipeleft="$dir = 'left'"
     action-swiperight="$dir = 'right'"
     action-longpress="file:/details.ham"
     action-doubletap="$count = $count + 1">
  Swipe, tap, or long-press me
</box>
```

`action-tap` is normalized to `click` internally. All supported triggers work.

### Unified Action Statements

The `action` attribute supports semicolon-separated statements mixing three types:

| Type | Format | Example |
|------|--------|---------|
| Media command | `#id.command` | `#v1.toggle` |
| Variable mutation | `$var = expr` | `$count = $count + 1` |
| Content fetch | URL | `file:/page.ham` |

```html
<!-- Chain media + variable + fetch in one gesture -->
<button action="#v1.pause; $playing = false; file:/stopped.ham">Stop All</button>
```

### actions (multiple)

JSON array for multiple actions on one element (alternative to `action-{trigger}`):

```html
<box actions='[
  {"action": "/left", "trigger": "swipeleft", "target": "#left"},
  {"action": "/right", "trigger": "swiperight", "target": "#right"}
]'>
```

### Additional Control Attributes

| Attribute | Description |
|-----------|-------------|
| `haptic` | Haptic feedback on interaction: light, medium, heavy, selection |
| `transition` | Swap animation: fade (default), slide-left, slide-right, slide-up, slide-down, scale, none |
| `transition-duration` | Swap animation duration in ms (default: 300) |
| `swipe-threshold` | Swipe velocity threshold in px/s (default: 100) |
| `source` | Content source type: network (default), file, folder |
| `delay` | Additional delay in ms after fetch before swap |
| `loading` / `indicator` | Loading indicator during fetch: blank = spinner, `#id` = named indicator |

### Complete Example

```html
<button action="/api/data" target="#result" swap="inner" trigger="click" method="GET">
  Load
</button>
<box id="result">Placeholder</box>
```

---

## Reactivity

Alpine.js-inspired reactive system.

### var-* / data-* (Variables)

Define reactive variables. `var-` and `data-` are interchangeable aliases.

```html
<box var-count="0" var-name="John">
<box data-count="0" data-name="John">  <!-- equivalent -->
```

Access with `$` prefix: `$count`, `$name`

### @click

Execute expression on tap/click.

```html
<button @click="$count = $count + 1">+</button>
<button @click="$open = !$open">Toggle</button>
<button @click="$a = 1; $b = 2">Multiple</button>
```

### @text

Bind text content to variable.

```html
<text @text="$count"></text>
<box @text="$message"></box>
```

### @show

Conditionally show/hide.

```html
<box @show="$visible">Shown when $visible is truthy</box>
```

### @on (Events)

Handle various events.

| Event | Description |
|-------|-------------|
| `@on.tap` | Tap gesture |
| `@on.longpress` | Long press |
| `@on.doubletap` | Double tap |
| `@on.swipeleft`, `@on.swiperight`, `@on.swipeup`, `@on.swipedown` | Swipes |
| `@on.shake` | Device shake (accelerometer) |
| `@on.rotate` | Device rotation (gyroscope) |
| `@on.orientation` | Screen orientation change |

Velocity modifiers: `.fast` (>500), `.slow` (<300)

```html
<box @on.swipeleft="$dir = 'left'" @on.swipeleft.fast="$dir = 'fast-left'">
```

### name (Form Variable Binding)

Form widgets (`<input>`, `<textarea>`, `<select>`, `<switch>`, `<checkbox>`, `<slider>`, `<radio-group>`) support `name` attribute for page-level variable binding:

```html
<input name="email" placeholder="Email"/>
<text @text="$email"></text>
```

#### Form Scoping

`<form>` creates a variable scope boundary — `name=` variables inside are local to that form. Any element with `id=` that contains `name=` descendants also creates a scope boundary.

```html
<form>
  <input name="x"/>  <!-- $x is local to this form -->
</form>
<input name="y"/>  <!-- $y is page-global -->
```

### Variable Scoping

Variables are scoped to element and descendants. Children can access/modify parent variables. Siblings cannot see each other's variables.

```html
<box var-global="root">
  <box var-child="a"><!-- sees $global, $child --></box>
  <box var-sibling="b"><!-- sees $global, $sibling, NOT $child --></box>
</box>
```

### Expressions

| Operation | Example |
|-----------|---------|
| Assignment | `$count = 10` |
| Addition | `$count = $count + 1` |
| Subtraction | `$count = $count - 1` |
| Multiplication | `$value = $value * 2` |
| Division | `$value = $value / 2` |
| Toggle | `$open = !$open` |
| String | `$status = 'active'` |
| Multiple | `$a = 1; $b = 2` |

### Global Variables

```html
<box global-theme="light">
```

Accessible from anywhere as `$theme`.

---

## Icons

Material Symbols icons with modifiers.

### Syntax

```
{icon_name} {style} {weight} {size} {color} {effects}
```

All parts except icon_name are optional.

### Styles

| Modifier | Description |
|----------|-------------|
| `rounded` | Rounded corners (default) |
| `outlined` | Outlined style |
| `sharp` | Sharp corners |

### Weight

100, 200, 300, 400, 500, 600, 700, 800, 900

### Size

| Modifier | Size |
|----------|------|
| `size-xs` | 12px |
| `size-sm` | 18px |
| `size-md` | 24px |
| `size-lg` | 30px |
| `size-xl` | 36px |
| `size-2xl` | 44px |
| `size-3xl` | 54px |
| `size-4xl` | 66px |
| `size-5xl` | 80px |
| `size-{n}` | Custom |

### Color

Named colors in icon modifiers: red, blue, green, yellow, orange, purple, pink, brown, grey, black, white, transparent

For extended colors (indigo, teal, cyan, etc.) in icons, use the `style` attribute or `color` attribute instead:

```html
<icon name="home size-26" style="text-indigo"></icon>
```

### Effects

| Modifier | Description |
|----------|-------------|
| `fill` | Filled/solid icon |
| `shadow` | Drop shadow |

### Examples

```html
<icon name="star"></icon>
<icon name="favorite rounded 700 size-lg red fill"></icon>
<icon name="cloud outlined blue shadow"></icon>
```

---

## Colors

### Named Colors

#### Material Colors (19)
Hand-curated shades via Flutter's MaterialColor:

**Primary:** red, blue, green, yellow, orange, purple, pink, brown

**Extended:** indigo, cyan, teal, amber, lime

**Compound:** light-blue, light-green, deep-orange, deep-purple, blue-grey

**Neutral:** grey/gray, black, white, transparent

#### All Named Colors (143+)
Every CSS Color Level 4 named color is supported. Examples:

coral, chartreuse, salmon, tomato, crimson, gold, navy, khaki, orchid, plum, lavender, peru, turquoise, sienna, violet, wheat, olive, maroon, aquamarine, bisque, chocolate, firebrick, honeydew, magenta, moccasin, royal-blue, sandy-brown, sea-green, sky-blue, spring-green, steel-blue, dodger-blue, dark-cyan, dark-salmon, deep-pink, forest-green, hot-pink, indian-red, light-coral, medium-purple, pale-green, rosy-brown, slate-blue, and many more.

Compound names use hyphens: `dark-slate-gray`, `light-coral`, `medium-purple`, `deep-sky-blue`

### Shades

`{color}-{50-900}` — Shade variants from light to dark.

**Material colors** use Flutter's predefined shades.

**All other named colors** use brightness-based shade generation — mixing toward white (lighter) or black (darker) to hit consistent brightness targets regardless of the original color.

```
bg-coral-50   (very light)
bg-coral-300  (light)
bg-coral-500  (medium)
bg-coral-700  (dark)
bg-coral-900  (very dark)
```

Shade 50 is always near-white, 900 is always near-black.

### RGB

`{r}-{g}-{b}` - Values 0-255

```
bg-255-100-50
```

### RGBA

`{r}-{g}-{b}-{opacity}` - Opacity 0.0-1.0

```
bg-255-100-50-0.5
```

### Hex

`#{RRGGBB}` or `{RRGGBB}` - With or without hash
`#{RRGGBBAA}` or `{RRGGBBAA}` - With alpha

```
bg-#FF5733
bg-FF5733
bg-#FF573380  (50% opacity)
```

---

## Configuration

### Flutter Entry Point

```dart
void main() {
  runApp(const MainApp(
    appProject: 'My App',
    privateKey: 'YOUR_KEY',
    developUrl: '/assets/html/main.html',
    stagingUrl: 'https://staging.example.com/app.html',
    releaseUrl: 'https://example.com/app.html',
  ));
}
```

### Environment Detection

| Build Mode | URL Used |
|------------|----------|
| Debug | developUrl |
| Profile | stagingUrl |
| Release | releaseUrl |

### URL Formats

| Format | Description |
|--------|-------------|
| `/assets/...` | Local asset |
| `https://...` | Network URL |

### Custom Config Values

Any attribute on `<config>` that isn't a known key is stored as a custom variable. Numeric values are auto-detected:

```html
<config max_items="5" discount="3.2" api_mode="sandbox"></config>
<!-- max_items → int 5, discount → double 3.2, api_mode → string "sandbox" -->
```

For complex values, use `json:` prefix: `settings='json:{"a":1}'`

---

## Appendix: Aliases

HypeApps supports shorthand aliases for both tags and style classes. Both forms are fully interchangeable.

### Tag Aliases

| Tag | Alias | Description |
|-----|-------|-------------|
| `<bold>`, `<strong>` | `<b>` | Bold text |
| `<italic>`, `<em>` | `<i>` | Italic text |
| `<drawer-left>` | `<drawer>` | Left navigation drawer |
| `<a>` | `<link>` | External link |
| `<img>` | `<image>` | Image element |
| `<radio-group>` | `<radios>` | Radio button group |
| `<expansion-panel>` | `<accordion>` | Expandable panel |
| `<pin>` | `<marker>` | Map marker |

```html
<!-- These are equivalent -->
<bold>Important</bold>
<b>Important</b>
<strong>Important</strong>

<italic>Emphasis</italic>
<i>Emphasis</i>
<em>Emphasis</em>
```

### Style Aliases

#### Spacing Shorthand

| Full Form | Shorthand | Description |
|-----------|-----------|-------------|
| `padding-{n}` | `p-{n}` | Padding all sides |
| `padding-top-{n}` | `pt-{n}` | Padding top |
| `padding-bottom-{n}` | `pb-{n}` | Padding bottom |
| `padding-left-{n}` | `pl-{n}` | Padding left |
| `padding-right-{n}` | `pr-{n}` | Padding right |
| `padding-horizontal-{n}` | `px-{n}` | Padding left + right |
| `padding-vertical-{n}` | `py-{n}` | Padding top + bottom |
| `margin-{n}` | `m-{n}` | Margin all sides |
| `margin-top-{n}` | `mt-{n}` | Margin top |
| `margin-bottom-{n}` | `mb-{n}` | Margin bottom |
| `margin-left-{n}` | `ml-{n}` | Margin left |
| `margin-right-{n}` | `mr-{n}` | Margin right |
| `margin-horizontal-{n}` | `mx-{n}` | Margin left + right |
| `margin-vertical-{n}` | `my-{n}` | Margin top + bottom |

```html
<!-- These are equivalent -->
<box style="padding-20">...</box>
<box style="p-20">...</box>

<box style="margin-top-10 margin-bottom-10">...</box>
<box style="my-10">...</box>
```

#### Font Size Aliases

All three forms are equivalent for each size:

| Size | Forms |
|------|-------|
| 10px | `font-xs`, `text-xs`, `font-size-xs` |
| 12px | `font-sm`, `text-sm`, `font-size-sm` |
| 14px | `font-base`, `text-base`, `font-size-base` |
| 16px | `font-lg`, `text-lg`, `font-size-lg` |
| 20px | `font-xl`, `text-xl`, `font-size-xl` |
| 24px | `font-2xl`, `text-2xl`, `font-size-2xl` |
| 30px | `font-3xl`, `text-3xl`, `font-size-3xl` |
| 36px | `font-4xl`, `text-4xl`, `font-size-4xl` |

```html
<!-- These are equivalent -->
<text style="font-lg">Large text</text>
<text style="text-lg">Large text</text>
<text style="font-size-lg">Large text</text>
```

#### Color Aliases

| Name | Alias |
|------|-------|
| `grey` | `gray` |

```html
<!-- These are equivalent -->
<box style="bg-grey">...</box>
<box style="bg-gray">...</box>
```

#### Alignment Aliases

Multiple forms for the same alignment:

| Alignment | All Valid Forms |
|-----------|-----------------|
| Center | `center` |
| Center Left | `left`, `l`, `cl`, `lc`, `center-left`, `left-center` |
| Center Right | `right`, `r`, `cr`, `rc`, `center-right`, `right-center` |
| Top Center | `top`, `tc`, `ct`, `top-center`, `center-top` |
| Bottom Center | `bottom`, `bc`, `cb`, `bottom-center`, `center-bottom` |
| Top Left | `tl`, `lt`, `top-left`, `left-top` |
| Top Right | `tr`, `rt`, `top-right`, `right-top` |
| Bottom Left | `bl`, `lb`, `bottom-left`, `left-bottom` |
| Bottom Right | `br`, `rb`, `bottom-right`, `right-bottom` |

```html
<!-- These are equivalent -->
<box style="align-tl">...</box>
<box style="align-top-left">...</box>
<box style="align-left-top">...</box>
```

#### Reactivity Aliases

| Attribute | Alias |
|-----------|-------|
| `var-*` | `data-*` |
