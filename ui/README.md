# @modufolio/media

The media-library UI for Appkit portfolio sites — album sidebar and tree,
media grid, inspectors, upload queue, and the composables that drive them.
Vue 3 + Inertia, built to sit on top of [`@modufolio/panel`](https://github.com/modufolio/panel).

## Install

```bash
npm install @modufolio/media
```

Peers you provide: `vue`, `@modufolio/panel`, `@inertiajs/vue3`, `@vueuse/core`
(and `lodash` if you use the composables that need it).

## Use

```js
import { AlbumSidebar, MediaGrid, useFavorites, useFeatured } from '@modufolio/media'
import '@modufolio/media/styles'
```

Components are styled with Tailwind utility classes — tell Tailwind v4 to
scan the package so they are generated (auto-detection skips node_modules):

```css
@source "../node_modules/@modufolio/media/dist";
```

On Tailwind v3, the equivalent is a `content` glob:
`'./node_modules/@modufolio/media/dist/**/*.js'`.

The small `styles` export carries the two scoped-style blocks that are not
utility classes.

## Develop

Apps in the Modufolio family compile this package from source by aliasing
`@modufolio/media` to `ui/src/index.ts` — the same arrangement as
`@modufolio/panel`. `npm run build` produces `dist/` for npm consumers.
