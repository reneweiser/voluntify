# Event Groups (Deprecated)

> **This concept has been replaced by Projects.** Event Groups no longer exist in Voluntify. All functionality previously provided by Event Groups is now handled by the Project entity, which is a mandatory top-level container for events.

See [Managing Projects](managing-projects.md) for the current documentation.

## Why the Change?

Event Groups were optional containers -- events could exist without one. This led to inconsistencies: shared resources (gear, custom fields, volunteers) had no natural home when events were ungrouped.

Projects solve this by making the container mandatory. Every event belongs to a project. Volunteers, gear, custom fields, and scanner configurations live at project level and are shared across all events within the project.

See GitHub Issue #52 for the full architectural rationale.