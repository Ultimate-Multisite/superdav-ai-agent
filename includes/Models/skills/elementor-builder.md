---
name: elementor-builder
description: "Use for safe Elementor document discovery, composition, editing, preview, and publication through registered official Elementor abilities."
compatibility: "Requires runtime-registered official elementor/* abilities; targets server-side abilities, not browser-local editor MCP tools."
---

# Elementor Builder

## When to Use

Use this skill when the user explicitly requests Elementor work, an existing page is an Elementor document, or an official `elementor/*` ability is available for the current task. Do not treat Avada Builder, BeBuilder, Cornerstone, Gutenberg, or arbitrary post content as Elementor documents.

This skill covers server-side official Elementor abilities. Browser-local editor MCP tools and unsaved editor state are a separate system; never claim access to them from this workflow.

## Required Workflow

1. **Discover capabilities first.** Call `sd-ai-agent/ability-search` for `elementor` and inspect the registered `elementor/*` abilities, their versions, schemas, and allowlist responses. Do not assume a V3/V4 node, widget, preview, publish, or mutation operation exists.
2. **Resolve and read the target document.** Identify the requested page before changing anything. When registered, use `elementor/get-page-structure` to read the current document and its returned revision, element IDs, and editor URL. Do not create a second page to work around an existing Elementor page.
3. **Look up supported widgets and schemas.** Use only widget, resource, and schema operations advertised by the capability response. Choose supported controls and values from the returned schemas; never guess widget names, control IDs, REST routes, or element JSON.
4. **Make one bounded change.** Use `elementor/manage-elements` for a focused element mutation or `elementor/build-composition` for a supported bounded composition. Preserve unrelated elements and settings. A create/read/settings catalog alone does not prove that widget mutation is available.
5. **Re-read after composition.** Call the available structure reader again after a composition or element-management call. Composition can replace element IDs, so never reuse IDs or revisions from the previous structure response.
6. **Preview before publication.** Request a preview through an advertised official Elementor preview operation and inspect its result. Publish only when the user requested publication, the preview succeeded, and the capability response confirms a publish operation. A successful write alone is not proof of a rendered result.

## Storage Boundary

- Never read or write `_elementor_data` directly.
- Never use generic post-meta mutation, direct database/PHP fallbacks, guessed REST routes, or a legacy JSON fallback for Elementor data.
- Never apply Gutenberg block-tree, block-content, template, or theme-generation tools to an Elementor document's storage.
- Do not replace an established Elementor page with a Gutenberg copy or a generated block theme unless the user explicitly requests that conversion.

## Partial Support

If the catalog exposes only create, read, or settings abilities, state exactly which operation is unavailable. Do not infer `elementor/manage-elements`, `elementor/build-composition`, preview, or publish support from `elementor/create-page` alone.

When widget editing is unavailable, preserve the document, report that conversational widget mutation is unavailable, and provide the returned Elementor editor URL when one is supplied. Do not claim a preview or publication that did not occur.

## Completion Check

Before reporting completion, confirm that the target document was resolved, the requested bounded change is visible in a successful official preview, and publication occurred only when requested. Report capability limitations honestly when any required operation was unavailable.
