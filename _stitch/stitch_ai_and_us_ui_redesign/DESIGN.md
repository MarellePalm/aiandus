---
name: Gardening Assistant Design System
colors:
  surface: '#f9f9f7'
  surface-dim: '#dadad8'
  surface-bright: '#f9f9f7'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f4f4f2'
  surface-container: '#eeeeec'
  surface-container-high: '#e8e8e6'
  surface-container-highest: '#e2e3e1'
  on-surface: '#1a1c1b'
  on-surface-variant: '#43483f'
  inverse-surface: '#2f3130'
  inverse-on-surface: '#f1f1ef'
  outline: '#74796e'
  outline-variant: '#c4c8bb'
  surface-tint: '#49663a'
  primary: '#476438'
  on-primary: '#ffffff'
  primary-container: '#5f7d4e'
  on-primary-container: '#f8ffee'
  inverse-primary: '#afd09a'
  secondary: '#5d5f5d'
  on-secondary: '#ffffff'
  secondary-container: '#e2e3e1'
  on-secondary-container: '#636563'
  tertiary: '#5d5c5b'
  on-tertiary: '#ffffff'
  tertiary-container: '#757474'
  on-tertiary-container: '#fffcfb'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#cbedb5'
  primary-fixed-dim: '#afd09a'
  on-primary-fixed: '#072100'
  on-primary-fixed-variant: '#324e24'
  secondary-fixed: '#e2e3e1'
  secondary-fixed-dim: '#c6c7c5'
  on-secondary-fixed: '#1a1c1b'
  on-secondary-fixed-variant: '#454746'
  tertiary-fixed: '#e5e2e1'
  tertiary-fixed-dim: '#c8c6c5'
  on-tertiary-fixed: '#1b1b1b'
  on-tertiary-fixed-variant: '#474746'
  background: '#f9f9f7'
  on-background: '#1a1c1b'
  surface-variant: '#e2e3e1'
typography:
  h1:
    fontFamily: Public Sans
    fontSize: 32px
    fontWeight: '700'
    lineHeight: '1.2'
  h2:
    fontFamily: Public Sans
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.3'
  h3:
    fontFamily: Public Sans
    fontSize: 20px
    fontWeight: '600'
    lineHeight: '1.4'
  body-lg:
    fontFamily: Public Sans
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Public Sans
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.5'
  label-md:
    fontFamily: Public Sans
    fontSize: 14px
    fontWeight: '600'
    lineHeight: '1.2'
    letterSpacing: 0.02em
  caption:
    fontFamily: Public Sans
    fontSize: 12px
    fontWeight: '400'
    lineHeight: '1.4'
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 8px
  xs: 4px
  sm: 12px
  md: 16px
  lg: 24px
  xl: 32px
  container-margin: 16px
  gutter: 16px
---

## Brand & Style

The design system is built on the principle of **Organic Minimalism**. It aims to bridge the gap between digital utility and the tactile nature of gardening. The aesthetic is clean and professional, avoiding futuristic trends in favor of a realistic, grounded interface that feels like a modern field journal.

The personality is helpful, calm, and organized. It targets hobbyist gardeners and students who need a reliable tool to track plant growth and seasonal tasks. By using ample whitespace and a soft, nature-inspired palette, the interface reduces cognitive load, allowing the user's plant photography and garden data to take center stage.

## Colors

The color palette is derived directly from botanical tones and natural materials. 

- **Primary (#648253):** A muted moss green used for primary actions, active states, and branding elements. It provides a natural "foliage" feel without being over-saturated.
- **Surface (#FBFBF9):** A warm, paper-like neutral used for the main application background to reduce eye strain compared to pure white.
- **Pure White (#FFFFFF):** Reserved for card backgrounds and input fields to create a clear visual hierarchy against the off-white background.
- **Text (#1C1C1C):** A deep charcoal for high-contrast legibility, ensuring all gardening notes and plant names are easily readable in outdoor lighting.

## Typography

This design system utilizes **Public Sans**, a typeface chosen for its exceptional clarity and institutional reliability. Its neutral character ensures that the app feels like a professional tool rather than a social media platform.

The typographic scale emphasizes a strong vertical rhythm. Headlines use a heavier weight to provide clear anchors for the user's eye, while body text uses a generous line height (1.5–1.6) to ensure that long descriptions of plant care or soil conditions remain accessible and easy to scan.

## Layout & Spacing

The layout follows a **fluid grid** model optimized for mobile devices. It utilizes an 8px base unit to maintain consistent proportions across all components.

- **Margins:** A standard 16px (md) margin is applied to the left and right of the screen container.
- **Gutter:** 16px spacing between cards in a list or grid view.
- **Stacking:** Elements within a card (e.g., image to text) should use 12px (sm) spacing, while major sections on a page should be separated by 24px (lg) or 32px (xl) to maintain a clean, airy feel.

## Elevation & Depth

This design system uses **tonal layers** combined with **ambient shadows** to create a sense of physical depth without looking overly "digital."

- **Level 0 (Background):** The warm neutral surface (#FBFBF9).
- **Level 1 (Cards/Elements):** Pure white (#FFFFFF) surfaces with a very soft, diffused shadow (0px offset, 8px blur, 4% opacity black). This makes the elements appear as if they are resting lightly on a table.
- **Interactive States:** When a user taps a card or button, the shadow should slightly deepen or the element should scale down by 1-2% to provide tactile feedback.

## Shapes

The shape language is defined by **rounded corners** that mimic the organic curves found in nature.

- **Standard Components:** Buttons, input fields, and small cards use a 0.5rem (8px) radius.
- **Large Containers:** Main plant profile cards or image containers use a 1rem (16px) radius to emphasize their importance and provide a softer visual aesthetic.
- **Icons:** Should be contained within circular or softly rounded square enclosures to maintain consistency with the UI's geometry.

## Components

### Cards (Kaardid)
The primary vessel for information. Cards feature a white background, 8px rounded corners, and a subtle shadow. For plant entries, use a top-aligned image followed by a text padding of 16px.

### Buttons (Nupud)
- **Primary:** Solid moss green (#648253) with white text. High contrast for "Lisa taim" (Add plant) or "Salvesta" (Save).
- **Secondary:** Outlined with a 1px border of the primary green or a light gray. Used for "Tühista" (Cancel).
- **Size:** Minimum touch target of 48px height for mobile accessibility.

### Input Fields (Sisestusväljad)
Clean, white backgrounds with a 1px light gray border (#E0E0E0). Labels should be in `label-md` style, positioned above the field.

### Chips (Sildid)
Small, rounded pills used for plant categories (e.g., "Köögivili," "Lilled"). Use a very light tint of the primary green with dark green text to maintain a soft look.

### Lists (Loendid)
For settings or task lists, use simple rows separated by 1px light gray dividers. Ensure each row has a minimum height of 56px to accommodate comfortable tapping.

### Progress Indicators
Use a soft green bar to show growth progress or task completion. The background of the progress bar should be a very pale version of the primary color.