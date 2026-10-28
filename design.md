# Ticket Management System - Design System Specification

## Color Palette

### Light Mode
- **Primary**: #4361ee (Blue)
- **Primary Hover**: #3a56d4
- **Secondary**: #7209b7 (Purple)
- **Background**: #f8f9fa
- **Surface**: #ffffff
- **Surface Secondary**: #f1f3f5
- **Surface Hover**: #f0f2f5
- **Text Primary**: #2b2d42
- **Text Secondary**: #495057
- **Muted**: #8d99ae
- **Border**: #e9ecef
- **Success**: #2a9d8f
- **Warning**: #e9c46a
- **Danger**: #e63946

### Dark Mode
- **Primary**: #6c8cff
- **Primary Hover**: #5a7aee
- **Secondary**: #b266e0
- **Background**: #141720
- **Surface**: #1e2230
- **Surface Secondary**: #252a3a
- **Surface Hover**: #2a3045
- **Text Primary**: #e2e8f0
- **Text Secondary**: #a0aec0
- **Muted**: #718096
- **Border**: #2d3448
- **Success**: #34d399
- **Warning**: #fbbf24
- **Danger**: #f87171

## Typography
- **Font Family**: 'Inter', 'Segoe UI', system-ui, sans-serif
- **Base Size**: 16px
- **Scale**: 
  - h1: 2.5rem (40px)
  - h2: 2rem (32px)
  - h3: 1.5rem (24px)
  - h4: 1.25rem (20px)
  - h5: 1.125rem (18px)
  - h6: 1rem (16px)
  - body: 1rem (16px)
  - small: 0.875rem (14px)
  - xs: 0.75rem (12px)

## Spacing System
- **Base Unit**: 4px
- **xs**: 4px
- **sm**: 8px
- **md**: 16px
- **lg**: 24px
- **xl**: 32px
- **2xl**: 48px
- **3xl**: 64px

## Border Radius
- **sm**: 4px
- **md**: 8px
- **lg**: 12px
- **xl**: 16px
- **full**: 9999px

## Shadows
- **sm**: 0 1px 2px rgba(0,0,0,0.05)
- **md**: 0 4px 6px rgba(0,0,0,0.07)
- **lg**: 0 10px 15px rgba(0,0,0,0.1)
- **xl**: 0 20px 25px rgba(0,0,0,0.15)

## Components

### Buttons
- **Primary**: Primary background, white text, rounded-md, px-4 py-2
- **Secondary**: Secondary background, white text
- **Outline**: Transparent background, primary border, primary text
- **Ghost**: Transparent background, text color
- **Danger**: Danger background, white text
- **Sizes**: sm (px-3 py-1.5), md (px-4 py-2), lg (px-6 py-3)

### Form Controls
- **Input**: Surface background, border, rounded-md, px-4 py-2.5
- **Focus**: Primary border, subtle primary shadow
- **Error**: Danger border, danger focus ring
- **Label**: Text-secondary, font-medium, mb-1.5

### Cards
- **Default**: Surface background, border, rounded-lg, shadow-sm
- **Hover**: Shadow-md transition

### Badges
- **Sizes**: sm (px-2 py-0.5), md (px-2.5 py-1)
- **Variants**: Primary, Secondary, Success, Warning, Danger, Info

### Tables
- **Header**: Surface-secondary background, text-secondary, font-semibold
- **Row Hover**: Surface-hover background
- **Borders**: Border color

### Sidebar
- **Width**: 260px (collapsed: 72px)
- **Background**: Surface
- **Active Item**: Primary background (10%), primary text, left border
- **Hover**: Surface-hover background

### Navbar
- **Height**: 64px
- **Background**: Surface
- **Border Bottom**: Border color

## Responsive Breakpoints
- **sm**: 640px
- **md**: 768px
- **lg**: 1024px
- **xl**: 1280px
- **2xl**: 1536px

## Animation/Transitions
- **Fast**: 150ms ease
- **Normal**: 250ms ease
- **Slow**: 350ms ease
- **Theme Transition**: 250ms ease for all color properties

## Accessibility
- **Focus Visible**: 2px solid primary, offset 2px
- **Color Contrast**: WCAG AA minimum
- **Keyboard Navigation**: All interactive elements
- **ARIA Labels**: All icon-only buttons
- **Reduced Motion**: Respect prefers-reduced-motion

## Page-Specific Guidelines

### Auth Pages (Login/Register)
- Centered card, max-width 420px
- Subtle background pattern/illustration
- Clear visual hierarchy
- Password strength indicator (register)

### Dashboard
- Stats cards with trend indicators
- Recent activity feed
- Quick actions
- Responsive grid (1 col mobile, 2 tablet, 4 desktop)

### Ticket List
- Advanced filters (collapsible on mobile)
- Sortable columns
- Row actions (view, edit)
- Pagination with page size selector
- Empty state illustration

### Ticket Details
- Two-column layout (desktop) / stacked (mobile)
- Main: Conversation thread with clear visual separation
- Sidebar: Metadata, attachments, history
- Reply form with file upload
- Real-time feel (optimistic UI)

### Create Ticket
- Stepper or single form
- Category with icons
- Priority with visual indicators
- Rich text description (optional)
- Drag-drop file upload

### Profile
- Avatar upload
- Two-column: info + security
- Password change with strength meter
- Notification preferences

### Admin Pages
- User management: table with role badges, actions dropdown
- Categories: drag-drop reorder, inline edit
- Reports: charts, date range picker, export

## Icon System
- Use inline SVGs (current approach)
- 20x20 default, 16x16 small, 24x24 large
- Stroke width: 2
- Current icons: Sun, Moon, Bell, Menu, Chevron, Search, User, Settings, Logout

## Dark Mode Implementation
- CSS custom properties (current approach)
- localStorage persistence
- System preference detection
- Smooth transitions
- All components tested in both modes

## Phase Implementation Plan

### Phase 1: Core Foundation & Auth Pages
- Update CSS custom properties with complete design tokens
- Improve button variants and states
- Enhance form controls with better focus/error states
- Refine auth pages (login/register) with better visual design
- Add loading states for buttons

### Phase 2: Layout & Navigation
- Improve sidebar with collapse/expand
- Enhance navbar with better user menu
- Add breadcrumbs
- Improve mobile navigation (drawer)
- Add page transitions

### Phase 3: Dashboard Pages
- User dashboard: enhanced stats cards, activity feed
- Staff dashboard: priority indicators, quick actions
- Admin dashboard: charts, better activity feed

### Phase 4: Ticket Management
- Ticket list: better filters, inline actions, bulk actions
- Ticket details: improved conversation threading, attachments
- Create ticket: better form UX, validation

### Phase 5: Profile & Settings
- Profile page: avatar, two-column layout
- Notification preferences
- Password/security section

### Phase 6: Admin Pages
- Users management: better table, modal forms
- Categories: drag-drop, inline edit
- Reports: charts, filters

### Phase 7: Polish & Accessibility
- Focus states, ARIA labels
- Reduced motion support
- Print styles
- Error/empty states
- Performance optimization