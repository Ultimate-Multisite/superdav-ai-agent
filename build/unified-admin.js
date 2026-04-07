/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/abilities-explorer/abilities-explorer-app.js"
/*!**********************************************************!*\
  !*** ./src/abilities-explorer/abilities-explorer-app.js ***!
  \**********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ AbilitiesExplorerApp)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);
/**
 * Abilities Explorer App
 *
 * Lists all registered WordPress abilities with name, description,
 * configuration status, required API keys, annotations, and output schema.
 * Abilities are grouped by category with collapsible sections.
 */

/**
 * WordPress dependencies
 */





/* global gratisAiAgentAbilities */

/**
 * Intent-to-style map for the custom Badge component.
 * Mirrors the colour conventions of the WordPress Badge component
 * without depending on it being present in the installed version.
 */

const BADGE_STYLES = {
  default: {
    background: '#f0f0f0',
    color: '#1e1e1e',
    border: '1px solid #c3c4c7'
  },
  info: {
    background: '#e8f4fd',
    color: '#0a4b78',
    border: '1px solid #72aee6'
  },
  success: {
    background: '#edfaef',
    color: '#1a4a1f',
    border: '1px solid #68de7c'
  },
  warning: {
    background: '#fcf9e8',
    color: '#4a3c00',
    border: '1px solid #f0b849'
  },
  error: {
    background: '#fce8e8',
    color: '#4a0000',
    border: '1px solid #f86368'
  }
};

/**
 * Custom inline Badge component with intent-based styling.
 * Replaces the @wordpress/components Badge which is unavailable
 * in older WordPress versions and causes a runtime crash.
 *
 * @param {Object} props
 * @param {string} [props.intent='default'] One of: default, info, success, warning, error.
 * @param {*}      props.children           Badge label content.
 */
function Badge({
  intent = 'default',
  children
}) {
  const style = {
    display: 'inline-flex',
    alignItems: 'center',
    padding: '2px 8px',
    borderRadius: '2px',
    fontSize: '11px',
    fontWeight: 600,
    lineHeight: '20px',
    whiteSpace: 'nowrap',
    ...(BADGE_STYLES[intent] || BADGE_STYLES.default)
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
    style: style,
    children: children
  });
}

/**
 * Renders a badge only when the annotation is active.
 *
 * @param {Object}  props
 * @param {string}  props.label  Badge label text.
 * @param {boolean} props.active Whether to render the badge.
 * @param {string}  props.intent Badge colour intent.
 */
function AnnotationBadge({
  label,
  active,
  intent
}) {
  if (!active) {
    return null;
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(Badge, {
    intent: intent,
    children: label
  });
}

/**
 * Single ability row component.
 *
 * @param {Object} props
 * @param {Object} props.ability Ability data object from the REST API.
 */
function AbilityRow({
  ability
}) {
  const [expanded, setExpanded] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const {
    name,
    label,
    description,
    category,
    param_count: paramCount,
    required_params: requiredParams,
    is_configured: isConfigured,
    required_api_keys: requiredApiKeys,
    annotations = {},
    output_schema: outputSchema,
    show_in_rest: showInRest
  } = ability;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
    className: "gratis-ai-agent-ability-row",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      className: "gratis-ai-agent-ability-row-header",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
        className: "gratis-ai-agent-ability-title",
        children: label || name
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
        className: "gratis-ai-agent-ability-name",
        children: name
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        className: "gratis-ai-agent-ability-badges",
        children: [isConfigured ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(Badge, {
          intent: "success",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Configured', 'gratis-ai-agent')
        }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(Badge, {
          intent: "warning",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Needs Setup', 'gratis-ai-agent')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(AnnotationBadge, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('destructive', 'gratis-ai-agent'),
          active: annotations.destructive,
          intent: "error"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(AnnotationBadge, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('readonly', 'gratis-ai-agent'),
          active: annotations.readonly,
          intent: "info"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(AnnotationBadge, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('idempotent', 'gratis-ai-agent'),
          active: annotations.idempotent,
          intent: "success"
        }), showInRest && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(Badge, {
          intent: "default",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('REST', 'gratis-ai-agent')
        })]
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      className: "gratis-ai-agent-ability-row-body",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
        className: "gratis-ai-agent-ability-category",
        children: category
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
        className: "gratis-ai-agent-ability-description",
        children: description || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No description available.', 'gratis-ai-agent')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        className: "gratis-ai-agent-ability-meta",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
          className: "gratis-ai-agent-ability-params",
          children: paramCount === 1 ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('1 parameter', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)(/* translators: %d: number of parameters */
          (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('%d parameters', 'gratis-ai-agent'), paramCount)
        }), requiredParams && requiredParams.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("span", {
          className: "gratis-ai-agent-ability-required",
          children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Required:', 'gratis-ai-agent'), ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("code", {
            children: requiredParams.join(', ')
          })]
        })]
      }), !isConfigured && requiredApiKeys && requiredApiKeys.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
        status: "warning",
        isDismissible: false,
        className: "gratis-ai-agent-ability-notice",
        children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Requires:', 'gratis-ai-agent'), ' ', requiredApiKeys.join(', '), ' ', gratisAiAgentAbilities?.settingsUrl && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("a", {
          href: gratisAiAgentAbilities.settingsUrl,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Configure in Settings', 'gratis-ai-agent')
        })]
      }), outputSchema && Object.keys(outputSchema).length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        className: "gratis-ai-agent-ability-schema-toggle",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "link",
          onClick: () => setExpanded(v => !v),
          "aria-expanded": expanded,
          children: expanded ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Hide output schema', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Show output schema', 'gratis-ai-agent')
        }), expanded && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("pre", {
          className: "gratis-ai-agent-ability-schema",
          children: JSON.stringify(outputSchema, null, 2)
        })]
      })]
    })]
  });
}

/**
 * Collapsible category section component.
 *
 * Renders a header with the category name and ability count badge,
 * and a collapsible body containing the ability rows.
 *
 * @param {Object}   props
 * @param {string}   props.category  Category name.
 * @param {Array}    props.abilities Abilities in this category.
 * @param {boolean}  props.open      Whether the section is expanded.
 * @param {Function} props.onToggle  Callback to toggle open/closed state.
 */
function CategorySection({
  category,
  abilities,
  open,
  onToggle
}) {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
    className: "gratis-ai-agent-abilities-category",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("button", {
      type: "button",
      className: "gratis-ai-agent-abilities-category-header",
      onClick: onToggle,
      "aria-expanded": open,
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
        className: "gratis-ai-agent-abilities-category-name",
        children: category
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
        className: "gratis-ai-agent-abilities-category-count",
        children: abilities.length
      })]
    }), open && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      className: "gratis-ai-agent-abilities-category-body",
      children: abilities.map(ability => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(AbilityRow, {
        ability: ability
      }, ability.name))
    })]
  });
}

/**
 * Main Abilities Explorer application component.
 *
 * Renders abilities grouped by category with:
 *   - A SearchControl that filters by name/description.
 *   - A SelectControl that filters by category.
 *   - Collapsible category sections with Expand all / Collapse all buttons.
 *   - A result count paragraph that updates as filters change.
 *
 * CSS classes used by E2E tests:
 *   .gratis-ai-agent-abilities-manager       — outer wrapper
 *   .gratis-ai-agent-abilities-search        — SearchControl wrapper
 *   .gratis-ai-agent-abilities-filters       — category SelectControl wrapper
 *   .gratis-ai-agent-abilities-count         — count paragraph
 *   .gratis-ai-agent-abilities-category      — per-category section
 *   .gratis-ai-agent-abilities-category-header — clickable header button
 *   .gratis-ai-agent-abilities-category-body   — collapsible body
 *   .gratis-ai-agent-abilities-category-count  — count badge in header
 */
function AbilitiesExplorerApp() {
  const [abilities, setAbilities] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [search, setSearch] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [categoryFilter, setCategoryFilter] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  // Map of category name → open state. True = expanded.
  const [openCategories, setOpenCategories] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
      path: '/gratis-ai-agent/v1/abilities/explorer'
    }).then(data => {
      setAbilities(data);
      setLoading(false);
    }).catch(err => {
      setError(err?.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to load abilities.', 'gratis-ai-agent'));
      setLoading(false);
    });
  }, []);

  // Derive unique categories for the filter dropdown.
  const categoryOptions = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('All Categories', 'gratis-ai-agent'),
    value: ''
  }, ...[...new Set(abilities.map(a => a.category))].sort().map(cat => ({
    label: cat,
    value: cat
  }))], [abilities]);

  // Filtered abilities based on search and category filter.
  const filtered = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    const searchLower = search.toLowerCase();
    return abilities.filter(ability => {
      const matchesSearch = !search || (ability.label || '').toLowerCase().includes(searchLower) || ability.name.toLowerCase().includes(searchLower) || (ability.description || '').toLowerCase().includes(searchLower);
      const matchesCategory = !categoryFilter || ability.category === categoryFilter;
      return matchesSearch && matchesCategory;
    });
  }, [abilities, search, categoryFilter]);

  // Group filtered abilities by category.
  const groupedByCategory = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    const groups = {};
    for (const ability of filtered) {
      if (!groups[ability.category]) {
        groups[ability.category] = [];
      }
      groups[ability.category].push(ability);
    }
    return groups;
  }, [filtered]);
  const sortedCategories = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => Object.keys(groupedByCategory).sort(), [groupedByCategory]);

  // When filtering is active, auto-expand all categories.
  const isFiltering = search !== '' || categoryFilter !== '';

  // Initialise open state when abilities load or categories change.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (abilities.length === 0) {
      return;
    }
    setOpenCategories(prev => {
      const next = {
        ...prev
      };
      for (const cat of sortedCategories) {
        if (!(cat in next)) {
          // Default to open.
          next[cat] = true;
        }
      }
      return next;
    });
  }, [abilities, sortedCategories]);

  // Auto-expand all categories when a filter is active.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (isFiltering) {
      setOpenCategories(prev => {
        const next = {
          ...prev
        };
        for (const cat of sortedCategories) {
          next[cat] = true;
        }
        return next;
      });
    }
  }, [isFiltering, sortedCategories]);
  const handleSearchChange = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(value => {
    setSearch(value);
  }, []);
  const handleCollapseAll = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setOpenCategories(prev => {
      const next = {
        ...prev
      };
      for (const cat of Object.keys(next)) {
        next[cat] = false;
      }
      return next;
    });
  }, []);
  const handleExpandAll = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setOpenCategories(prev => {
      const next = {
        ...prev
      };
      for (const cat of Object.keys(next)) {
        next[cat] = true;
      }
      return next;
    });
  }, []);
  const handleToggleCategory = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(category => {
    setOpenCategories(prev => ({
      ...prev,
      [category]: !prev[category]
    }));
  }, []);
  if (loading) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      className: "gratis-ai-agent-abilities-loading",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Spinner, {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Loading abilities…', 'gratis-ai-agent')
      })]
    });
  }
  if (error) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
      status: "error",
      isDismissible: false,
      children: error
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
    className: "gratis-ai-agent-abilities-manager",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      className: "gratis-ai-agent-abilities-toolbar",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        className: "gratis-ai-agent-abilities-controls",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          className: "gratis-ai-agent-abilities-search",
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SearchControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search abilities', 'gratis-ai-agent'),
            value: search,
            onChange: handleSearchChange,
            placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search by name or description…', 'gratis-ai-agent')
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          className: "gratis-ai-agent-abilities-filters",
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Category', 'gratis-ai-agent'),
            value: categoryFilter,
            options: categoryOptions,
            onChange: setCategoryFilter
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
          className: "gratis-ai-agent-abilities-bulk-actions",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            variant: "tertiary",
            onClick: handleCollapseAll,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Collapse all', 'gratis-ai-agent')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            variant: "tertiary",
            onClick: handleExpandAll,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Expand all', 'gratis-ai-agent')
          })]
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
        className: "gratis-ai-agent-abilities-count",
        children: filtered.length === abilities.length ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)(/* translators: %d: total number of abilities */
        (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('%d abilities registered', 'gratis-ai-agent'), abilities.length) : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)(/* translators: 1: filtered count, 2: total count */
        (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Showing %1$d of %2$d abilities', 'gratis-ai-agent'), filtered.length, abilities.length)
      })]
    }), filtered.length === 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
      className: "gratis-ai-agent-abilities-no-results",
      children: abilities.length === 0 ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No abilities are registered.', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No abilities match your current filters.', 'gratis-ai-agent')
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      className: "gratis-ai-agent-abilities-list",
      children: sortedCategories.map(category => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(CategorySection, {
        category: category,
        abilities: groupedByCategory[category],
        open: !!openCategories[category],
        onToggle: () => handleToggleCategory(category)
      }, category))
    })]
  });
}

/***/ },

/***/ "./src/components/error-boundary.js"
/*!******************************************!*\
  !*** ./src/components/error-boundary.js ***!
  \******************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ ErrorBoundary)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);
/**
 * WordPress dependencies
 */




/**
 * ErrorBoundary catches JavaScript errors anywhere in its child component tree,
 * logs those errors, and displays a user-friendly fallback UI instead of
 * crashing the entire application.
 *
 * Usage:
 *   <ErrorBoundary>
 *     <MyComponent />
 *   </ErrorBoundary>
 *
 * Or with a custom fallback label:
 *   <ErrorBoundary label={ __( 'Message List', 'gratis-ai-agent' ) }>
 *     <MessageList />
 *   </ErrorBoundary>
 */

class ErrorBoundary extends _wordpress_element__WEBPACK_IMPORTED_MODULE_0__.Component {
  constructor(props) {
    super(props);
    this.state = {
      hasError: false,
      error: null
    };
    this.handleReset = this.handleReset.bind(this);
  }
  static getDerivedStateFromError(error) {
    return {
      hasError: true,
      error
    };
  }
  componentDidCatch(error, info) {
    // Log to console so developers can see the full stack trace.
    // eslint-disable-next-line no-console
    console.error('[AI Agent] Component error:', error, info);
  }
  handleReset() {
    this.setState({
      hasError: false,
      error: null
    });
  }
  render() {
    if (this.state.hasError) {
      const {
        label
      } = this.props;
      const areaLabel = label || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('This section', 'gratis-ai-agent');
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
        className: "gratis-ai-agent-error-boundary",
        role: "alert",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("p", {
          className: "gratis-ai-agent-error-boundary-message",
          children: [areaLabel, ' ', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('encountered an unexpected error.', 'gratis-ai-agent')]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          variant: "secondary",
          onClick: this.handleReset,
          className: "gratis-ai-agent-error-boundary-retry",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Try again', 'gratis-ai-agent')
        })]
      });
    }
    return this.props.children;
  }
}

/***/ },

/***/ "./src/components/model-pricing-selector.js"
/*!**************************************************!*\
  !*** ./src/components/model-pricing-selector.js ***!
  \**************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ModelPricingHint: () => (/* binding */ ModelPricingHint),
/* harmony export */   buildPricedModelOptions: () => (/* binding */ buildPricedModelOptions),
/* harmony export */   "default": () => (/* binding */ ModelPricingSelector)
/* harmony export */ });
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);
/**
 * Model pricing selector with tier grouping and cost hints.
 *
 * Mirrors the pricing data in CostCalculator.php. When the PHP-side pricing
 * changes, update MODEL_CATALOG below to match.
 *
 * Average session token assumptions (used for estimated session cost):
 *   - Input:  8,000 tokens  (system prompt + context + user messages)
 *   - Output: 2,000 tokens  (assistant replies)
 */

/**
 * WordPress dependencies
 */



/**
 * Pricing per million tokens [input, output] in USD.
 * Keep in sync with CostCalculator::PRICING.
 *
 * @type {Object.<string, [number, number]>}
 */

const PRICING = {
  // Claude models.
  'claude-haiku-4': [0.8, 4.0],
  'claude-sonnet-4': [3.0, 15.0],
  'claude-opus-4': [15.0, 75.0],
  'claude-3-5-haiku-20241022': [0.8, 4.0],
  // GPT-4o models.
  'gpt-4o-mini': [0.15, 0.6],
  'gpt-4o': [2.5, 10.0],
  // GPT-4.1 models.
  'gpt-4.1-nano': [0.1, 0.4],
  'gpt-4.1-mini': [0.4, 1.6],
  'gpt-4.1': [2.0, 8.0],
  // o-series models.
  'o3-mini': [1.1, 4.4],
  'o4-mini': [1.1, 4.4],
  o3: [10.0, 40.0],
  // Gemini models (OpenRouter IDs use google/ prefix).
  'google/gemini-2.5-flash-preview': [0.3, 2.5],
  'google/gemini-2.5-flash-lite-preview': [0.1, 0.4],
  'gemini-2.0-flash': [0.1, 0.4],
  'gemini-2.0-flash-lite': [0.075, 0.3],
  'gemini-2.5-pro-preview-05-06': [1.25, 10.0],
  'gemini-1.5-pro': [1.25, 5.0],
  'gemini-1.5-flash': [0.075, 0.3]
};

/**
 * Average tokens per session (input + output) used for cost estimates.
 */
const AVG_SESSION_INPUT_TOKENS = 8000;
const AVG_SESSION_OUTPUT_TOKENS = 2000;

/**
 * Tier definitions: Budget / Standard / Premium.
 * Threshold is the maximum input price per million tokens for that tier.
 */
const TIERS = [{
  id: 'budget',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Budget', 'gratis-ai-agent'),
  maxInput: 0.5
}, {
  id: 'standard',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Standard', 'gratis-ai-agent'),
  maxInput: 3.0
}, {
  id: 'premium',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Premium', 'gratis-ai-agent'),
  maxInput: Infinity
}];

/**
 * Canonical model catalogue with display metadata.
 * Only models listed here appear in the grouped selector.
 * Models returned by the REST API but not in this list fall back to the
 * plain SelectControl label (no pricing hint).
 *
 * @type {Array<{id: string, provider: string, name: string, note: string}>}
 */
const MODEL_CATALOG = [
// Anthropic
{
  id: 'claude-haiku-4',
  provider: 'anthropic',
  name: 'Claude Haiku 4',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('fastest', 'gratis-ai-agent')
}, {
  id: 'claude-3-5-haiku-20241022',
  provider: 'anthropic',
  name: 'Claude 3.5 Haiku',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('budget', 'gratis-ai-agent')
}, {
  id: 'claude-sonnet-4',
  provider: 'anthropic',
  name: 'Claude Sonnet 4',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('balanced', 'gratis-ai-agent')
}, {
  id: 'claude-opus-4',
  provider: 'anthropic',
  name: 'Claude Opus 4',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('most capable', 'gratis-ai-agent')
},
// OpenAI GPT-4.1
{
  id: 'gpt-4.1-nano',
  provider: 'openai',
  name: 'GPT-4.1 Nano',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('best value', 'gratis-ai-agent')
}, {
  id: 'gpt-4.1-mini',
  provider: 'openai',
  name: 'GPT-4.1 Mini',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('fast & affordable', 'gratis-ai-agent')
}, {
  id: 'gpt-4.1',
  provider: 'openai',
  name: 'GPT-4.1',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('high quality', 'gratis-ai-agent')
},
// OpenAI GPT-4o
{
  id: 'gpt-4o-mini',
  provider: 'openai',
  name: 'GPT-4o Mini',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('affordable', 'gratis-ai-agent')
}, {
  id: 'gpt-4o',
  provider: 'openai',
  name: 'GPT-4o',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('multimodal', 'gratis-ai-agent')
},
// OpenAI o-series
{
  id: 'o4-mini',
  provider: 'openai',
  name: 'o4-mini',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('reasoning', 'gratis-ai-agent')
}, {
  id: 'o3-mini',
  provider: 'openai',
  name: 'o3-mini',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('reasoning', 'gratis-ai-agent')
}, {
  id: 'o3',
  provider: 'openai',
  name: 'o3',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('advanced reasoning', 'gratis-ai-agent')
},
// Google Gemini
{
  id: 'gemini-2.0-flash-lite',
  provider: 'google',
  name: 'Gemini 2.0 Flash Lite',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('best value', 'gratis-ai-agent')
}, {
  id: 'google/gemini-2.5-flash-lite-preview',
  provider: 'google',
  name: 'Gemini 2.5 Flash Lite',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('budget', 'gratis-ai-agent')
}, {
  id: 'gemini-2.0-flash',
  provider: 'google',
  name: 'Gemini 2.0 Flash',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('fast & affordable', 'gratis-ai-agent')
}, {
  id: 'google/gemini-2.5-flash-preview',
  provider: 'google',
  name: 'Gemini 2.5 Flash',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('fast & capable', 'gratis-ai-agent')
}, {
  id: 'gemini-1.5-flash',
  provider: 'google',
  name: 'Gemini 1.5 Flash',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('affordable', 'gratis-ai-agent')
}, {
  id: 'gemini-1.5-pro',
  provider: 'google',
  name: 'Gemini 1.5 Pro',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('high quality', 'gratis-ai-agent')
}, {
  id: 'gemini-2.5-pro-preview-05-06',
  provider: 'google',
  name: 'Gemini 2.5 Pro',
  note: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('most capable', 'gratis-ai-agent')
}];

/**
 * Determine the tier for a model based on its input price per million tokens.
 *
 * @param {string} modelId - Model identifier.
 * @return {Object} Tier object from TIERS.
 */
function getTier(modelId) {
  const pricing = PRICING[modelId];
  if (!pricing) {
    return TIERS[1]; // Default to Standard when unknown.
  }
  const inputPrice = pricing[0];
  return TIERS.find(t => inputPrice <= t.maxInput) || TIERS[2];
}

/**
 * Format a price per million tokens as a short human-readable string.
 *
 * @param {number} price - Price per million tokens in USD.
 * @return {string} Formatted string, e.g. "$0.10/M" or "$3.00/M".
 */
function formatPrice(price) {
  if (price < 1) {
    return `$${price.toFixed(2)}/M`;
  }
  return `$${price.toFixed(2)}/M`;
}

/**
 * Estimate the cost of an average session for a given model.
 *
 * @param {string} modelId - Model identifier.
 * @return {string|null} Formatted cost string, e.g. "~$0.01/session", or null.
 */
function estimateSessionCost(modelId) {
  const pricing = PRICING[modelId];
  if (!pricing) {
    return null;
  }
  const [inputPricePerM, outputPricePerM] = pricing;
  const cost = AVG_SESSION_INPUT_TOKENS / 1_000_000 * inputPricePerM + AVG_SESSION_OUTPUT_TOKENS / 1_000_000 * outputPricePerM;
  if (cost < 0.001) {
    return `~$${(cost * 1000).toFixed(2)}m/session`; // millicents
  }
  if (cost < 0.01) {
    return `~$${cost.toFixed(4)}/session`;
  }
  return `~$${cost.toFixed(3)}/session`;
}

/**
 * Build the option label for a model including pricing hint.
 *
 * @param {Object} catalogEntry - Entry from MODEL_CATALOG.
 * @return {string} Label string for the SelectControl option.
 */
function buildModelLabel(catalogEntry) {
  const {
    id,
    name,
    note
  } = catalogEntry;
  const pricing = PRICING[id];
  if (!pricing) {
    return note ? `${name} — ${note}` : name;
  }
  const [inputPrice, outputPrice] = pricing;
  const sessionCost = estimateSessionCost(id);
  const priceHint = `${formatPrice(inputPrice)} in / ${formatPrice(outputPrice)} out`;
  const parts = [name];
  if (note) {
    parts.push(note);
  }
  parts.push(priceHint);
  if (sessionCost) {
    parts.push(sessionCost);
  }
  return parts.join(' — ');
}

/**
 * Build grouped SelectControl options from the available model list.
 *
 * Groups models by provider, then by tier (Budget / Standard / Premium).
 * Models not in MODEL_CATALOG are appended at the end under their provider
 * with no pricing hint.
 *
 * @param {Array}  models         - Models from the REST API for the selected provider.
 * @param {string} providerName   - Display name of the selected provider.
 * @param {string} [defaultLabel] - Label for the empty/default option.
 * @return {Array} Array of option objects for SelectControl.
 */
function buildPricedModelOptions(models, providerName, defaultLabel) {
  const options = [{
    label: defaultLabel || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('(default)', 'gratis-ai-agent'),
    value: ''
  }];
  if (!models || !models.length) {
    return options;
  }

  // Index catalog entries by model ID for fast lookup.
  const catalogById = {};
  MODEL_CATALOG.forEach(entry => {
    catalogById[entry.id] = entry;
  });

  // Separate models into catalogued (with pricing) and uncatalogued.
  const catalogued = [];
  const uncatalogued = [];
  models.forEach(m => {
    if (catalogById[m.id]) {
      catalogued.push({
        model: m,
        entry: catalogById[m.id]
      });
    } else {
      uncatalogued.push(m);
    }
  });

  // Group catalogued models by tier.
  const byTier = {};
  TIERS.forEach(t => {
    byTier[t.id] = [];
  });
  catalogued.forEach(({
    model,
    entry
  }) => {
    const tier = getTier(entry.id);
    byTier[tier.id].push({
      model,
      entry
    });
  });

  // Emit options grouped by tier.
  TIERS.forEach(tier => {
    const tierModels = byTier[tier.id];
    if (!tierModels.length) {
      return;
    }

    // Group header as a disabled option (visual separator).
    options.push({
      label: `── ${tier.label} ──`,
      value: `__tier_${tier.id}__`,
      disabled: true
    });
    tierModels.forEach(({
      model,
      entry
    }) => {
      options.push({
        label: buildModelLabel(entry),
        value: model.id
      });
    });
  });

  // Append uncatalogued models under a separator.
  if (uncatalogued.length) {
    options.push({
      label: `── ${providerName || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Other', 'gratis-ai-agent')} ──`,
      value: '__tier_other__',
      disabled: true
    });
    uncatalogued.forEach(m => {
      options.push({
        label: m.name || m.id,
        value: m.id
      });
    });
  }
  return options;
}

/**
 * Pricing hint badge shown below the model selector.
 *
 * Displays the tier label, per-million token prices, and estimated session
 * cost for the currently selected model.
 *
 * @param {Object} props         - Component props.
 * @param {string} props.modelId - Currently selected model ID.
 * @return {JSX.Element|null} Pricing hint element, or null when no data.
 */
function ModelPricingHint({
  modelId
}) {
  if (!modelId) {
    return null;
  }
  const pricing = PRICING[modelId];
  if (!pricing) {
    return null;
  }
  const [inputPrice, outputPrice] = pricing;
  const tier = getTier(modelId);
  const sessionCost = estimateSessionCost(modelId);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("p", {
    className: "gratis-ai-agent-model-pricing-hint",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("span", {
      className: `gratis-ai-agent-model-pricing-hint__tier gratis-ai-agent-model-pricing-hint__tier--${tier.id}`,
      children: tier.label
    }), ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("span", {
      className: "gratis-ai-agent-model-pricing-hint__prices",
      children: [formatPrice(inputPrice), ' ', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('input', 'gratis-ai-agent'), ' / ', formatPrice(outputPrice), ' ', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('output', 'gratis-ai-agent')]
    }), sessionCost && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.Fragment, {
      children: [' · ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("span", {
        className: "gratis-ai-agent-model-pricing-hint__session",
        children: [sessionCost, ' ', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('(avg. session estimate)', 'gratis-ai-agent')]
      })]
    })]
  });
}

/**
 * Model selector with pricing hints and tier grouping.
 *
 * Drop-in replacement for a plain SelectControl when selecting a model in
 * the Settings General tab. Shows pricing hints inline in the option labels
 * and renders a pricing badge below the selector for the selected model.
 *
 * @param {Object}   props              - Component props.
 * @param {string}   props.id           - ID forwarded to the underlying SelectControl.
 * @param {string}   props.label        - SelectControl label.
 * @param {string}   props.value        - Currently selected model ID.
 * @param {Array}    props.models       - Models from the REST API.
 * @param {string}   props.providerName - Display name of the selected provider.
 * @param {Function} props.onChange     - Change handler.
 * @return {JSX.Element} Model selector with pricing hints.
 */
function ModelPricingSelector({
  id,
  label,
  value,
  models,
  providerName,
  onChange
}) {
  const options = buildPricedModelOptions(models, providerName, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('(default)', 'gratis-ai-agent'));
  const handleChange = newValue => {
    // Ignore clicks on disabled group-header options.
    if (newValue.startsWith('__tier_')) {
      return;
    }
    onChange(newValue);
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
    className: "gratis-ai-agent-model-pricing-selector",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.SelectControl, {
      id: id,
      label: label,
      value: value,
      options: options,
      onChange: handleChange,
      __nextHasNoMarginBottom: true
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(ModelPricingHint, {
      modelId: value
    })]
  });
}

/***/ },

/***/ "./src/components/use-text-to-speech.js"
/*!**********************************************!*\
  !*** ./src/components/use-text-to-speech.js ***!
  \**********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ useTextToSpeech),
/* harmony export */   isTTSSupported: () => (/* binding */ isTTSSupported),
/* harmony export */   useAvailableVoices: () => (/* binding */ useAvailableVoices)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/**
 * Text-to-speech hook using the Web Speech API (SpeechSynthesis).
 *
 * Provides a simple interface to speak text aloud, cancel speech,
 * and query browser support. Voice, rate, and pitch are configurable.
 *
 * @module use-text-to-speech
 */

/**
 * WordPress dependencies
 */


/**
 * Whether the browser supports the Web Speech API SpeechSynthesis interface.
 *
 * @type {boolean}
 */
const isTTSSupported = typeof window !== 'undefined' && 'speechSynthesis' in window;

/**
 * Return the list of available SpeechSynthesis voices.
 * Voices load asynchronously in some browsers (Chrome), so this hook
 * subscribes to the `voiceschanged` event and re-reads the list.
 *
 * @return {Object[]} Available voices (may be empty until loaded).
 */
function useAvailableVoices() {
  const [voices, setVoices] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(() => isTTSSupported ? window.speechSynthesis.getVoices() : []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (!isTTSSupported) {
      return;
    }
    const update = () => setVoices(window.speechSynthesis.getVoices());

    // Chrome fires voiceschanged; Firefox populates synchronously.
    window.speechSynthesis.addEventListener('voiceschanged', update);
    update();
    return () => {
      window.speechSynthesis.removeEventListener('voiceschanged', update);
    };
  }, []);
  return voices;
}

/**
 * Strip markdown syntax from text before speaking it.
 * Removes code fences, inline code, headers, bold/italic markers, links,
 * and leading list/blockquote characters so the spoken output is clean prose.
 *
 * @param {string} text Raw markdown text.
 * @return {string} Plain text suitable for speech synthesis.
 */
function stripMarkdown(text) {
  return text
  // Fenced code blocks — replace with a brief spoken label.
  .replace(/```[\s\S]*?```/g, ' code block. ')
  // Inline code.
  .replace(/`[^`]+`/g, m => m.slice(1, -1))
  // ATX headings.
  .replace(/^#{1,6}\s+/gm, '')
  // Bold / italic.
  .replace(/\*{1,3}([^*]+)\*{1,3}/g, '$1').replace(/_{1,3}([^_]+)_{1,3}/g, '$1')
  // Links — keep the label.
  .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
  // Images.
  .replace(/!\[[^\]]*\]\([^)]+\)/g, '')
  // Blockquote markers.
  .replace(/^>\s*/gm, '')
  // List markers.
  .replace(/^[\s]*[-*+]\s+/gm, '').replace(/^[\s]*\d+\.\s+/gm, '')
  // Horizontal rules.
  .replace(/^[-*_]{3,}\s*$/gm, '')
  // Collapse multiple blank lines.
  .replace(/\n{3,}/g, '\n\n').trim();
}

/**
 * Hook that provides text-to-speech functionality via SpeechSynthesis.
 *
 * @param {Object} options               - Configuration options.
 * @param {string} [options.voiceURI=''] - URI of the voice to use (empty = browser default).
 * @param {number} [options.rate=1]      - Speech rate (0.1–10, default 1).
 * @param {number} [options.pitch=1]     - Speech pitch (0–2, default 1).
 * @return {{
 *   isSpeaking: boolean,
 *   speak: (text: string) => void,
 *   cancel: () => void,
 *   isSupported: boolean,
 * }} TTS controls.
 */
function useTextToSpeech({
  voiceURI = '',
  rate = 1,
  pitch = 1
} = {}) {
  const [isSpeaking, setIsSpeaking] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const utteranceRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);

  // Cancel any in-progress speech when the component unmounts.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    return () => {
      if (isTTSSupported) {
        window.speechSynthesis.cancel();
      }
    };
  }, []);
  const speak = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(text => {
    if (!isTTSSupported || !text) {
      return;
    }

    // Cancel any current speech before starting new.
    window.speechSynthesis.cancel();
    const plain = stripMarkdown(text);
    if (!plain) {
      return;
    }
    const utterance = new SpeechSynthesisUtterance(plain);
    utterance.rate = rate;
    utterance.pitch = pitch;

    // Resolve voice by URI if specified.
    if (voiceURI) {
      const voices = window.speechSynthesis.getVoices();
      const match = voices.find(v => v.voiceURI === voiceURI);
      if (match) {
        utterance.voice = match;
      }
    }
    utterance.onstart = () => setIsSpeaking(true);
    utterance.onend = () => setIsSpeaking(false);
    utterance.onerror = () => setIsSpeaking(false);
    utteranceRef.current = utterance;
    window.speechSynthesis.speak(utterance);
  }, [voiceURI, rate, pitch]);
  const cancel = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    if (isTTSSupported) {
      window.speechSynthesis.cancel();
      setIsSpeaking(false);
    }
  }, []);
  return {
    isSpeaking,
    speak,
    cancel,
    isSupported: isTTSSupported
  };
}

/***/ },

/***/ "./src/settings-page/abilities-manager.js"
/*!************************************************!*\
  !*** ./src/settings-page/abilities-manager.js ***!
  \************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ AbilitiesManager)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);
/**
 * Abilities Manager
 *
 * Settings > Abilities tab: search filter, category grouping with collapsible
 * sections, and per-ability permission selects (Auto / Confirm / Disabled).
 */

/**
 * WordPress dependencies
 */




/**
 * Permission options shared across all ability selects.
 */

const PERMISSION_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Auto (always allow)', 'gratis-ai-agent'),
  value: 'auto'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Confirm (ask before use)', 'gratis-ai-agent'),
  value: 'confirm'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Disabled', 'gratis-ai-agent'),
  value: 'disabled'
}];

/**
 * Single collapsible category section.
 *
 * @param {Object}   props
 * @param {string}   props.category        Category label.
 * @param {Array}    props.abilities       Abilities in this category.
 * @param {Object}   props.toolPermissions Current tool_permissions map.
 * @param {Function} props.onPermChange    Called with (abilityName, newValue).
 * @param {boolean}  props.defaultOpen     Whether the section starts expanded.
 * @param {boolean}  props.isFiltering     Whether a search/category filter is active.
 */
function AbilityCategorySection({
  category,
  abilities,
  toolPermissions,
  onPermChange,
  defaultOpen,
  isFiltering
}) {
  const [open, setOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(defaultOpen);

  // Sync open state when the parent changes defaultOpen (e.g. collapse/expand all).
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    setOpen(defaultOpen);
  }, [defaultOpen]);

  // Force-open the section whenever filtering becomes active so that a
  // section manually collapsed while allOpen===true is not left hidden
  // when the user starts a search or category filter.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (isFiltering) {
      setOpen(true);
    }
  }, [isFiltering]);

  // Count non-default (non-auto) permissions in this category.
  const nonDefaultCount = abilities.filter(a => {
    const perm = toolPermissions[a.name];
    return perm && perm !== 'auto';
  }).length;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
    className: "gratis-ai-agent-abilities-category",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("button", {
      type: "button",
      className: "gratis-ai-agent-abilities-category-header",
      onClick: () => setOpen(v => !v),
      "aria-expanded": open,
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("span", {
        className: "gratis-ai-agent-abilities-category-chevron",
        children: open ? '▾' : '▸'
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("span", {
        className: "gratis-ai-agent-abilities-category-name",
        children: category
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("span", {
        className: "gratis-ai-agent-abilities-category-count",
        children: abilities.length
      }), nonDefaultCount > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("span", {
        className: "gratis-ai-agent-abilities-category-badge",
        children: [nonDefaultCount, ' ', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('customised', 'gratis-ai-agent')]
      })]
    }), open && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
      className: "gratis-ai-agent-abilities-category-body",
      children: abilities.map(ability => {
        const currentPerm = toolPermissions[ability.name] || 'auto';
        return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
          className: "gratis-ai-agent-ability-row",
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
            label: ability.label || ability.name,
            help: ability.description || '',
            value: currentPerm,
            options: PERMISSION_OPTIONS,
            onChange: v => onPermChange(ability.name, v),
            __nextHasNoMarginBottom: true
          })
        }, ability.name);
      })
    })]
  });
}

/**
 * Abilities Manager component.
 *
 * @param {Object}   props
 * @param {Array}    props.abilities       All registered abilities from the API.
 * @param {Object}   props.toolPermissions Current tool_permissions map from settings.
 * @param {Function} props.onPermChange    Called with (abilityName, newValue).
 */
function AbilitiesManager({
  abilities,
  toolPermissions,
  onPermChange
}) {
  const [search, setSearch] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [categoryFilter, setCategoryFilter] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [allOpen, setAllOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  // Track which categories have been manually toggled.
  const [openOverrides, setOpenOverrides] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const handleSearchChange = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(value => {
    setSearch(value);
  }, []);

  // Derive unique sorted categories for the filter dropdown.
  const categoryOptions = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    const cats = [...new Set(abilities.map(a => a.category || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('General', 'gratis-ai-agent')))].sort();
    return [{
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('All Categories', 'gratis-ai-agent'),
      value: ''
    }, ...cats.map(c => ({
      label: c,
      value: c
    }))];
  }, [abilities]);

  // Filter abilities by search + category.
  const filtered = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    const searchLower = search.toLowerCase();
    return abilities.filter(ability => {
      const matchesSearch = !search || (ability.label || '').toLowerCase().includes(searchLower) || ability.name.toLowerCase().includes(searchLower) || (ability.description || '').toLowerCase().includes(searchLower);
      const abilityCategory = ability.category || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('General', 'gratis-ai-agent');
      const matchesCategory = !categoryFilter || abilityCategory === categoryFilter;
      return matchesSearch && matchesCategory;
    });
  }, [abilities, search, categoryFilter]);

  // Group filtered abilities by category, preserving sort order.
  const grouped = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    const map = {};
    filtered.forEach(ability => {
      const cat = ability.category || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('General', 'gratis-ai-agent');
      if (!map[cat]) {
        map[cat] = [];
      }
      map[cat].push(ability);
    });
    // Return sorted array of [category, abilities[]] pairs.
    return Object.entries(map).sort((a, b) => a[0].localeCompare(b[0]));
  }, [filtered]);
  const handleExpandAll = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setAllOpen(true);
    setOpenOverrides({});
  }, []);
  const handleCollapseAll = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setAllOpen(false);
    setOpenOverrides({});
  }, []);
  if (abilities.length === 0) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No abilities registered.', 'gratis-ai-agent')
    });
  }
  const isFiltering = search || categoryFilter;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
    className: "gratis-ai-agent-abilities-manager",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
      className: "gratis-ai-agent-abilities-toolbar",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
        className: "gratis-ai-agent-abilities-search",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SearchControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search abilities', 'gratis-ai-agent'),
          value: search,
          onChange: handleSearchChange,
          placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search by name or description…', 'gratis-ai-agent')
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
        className: "gratis-ai-agent-abilities-filters",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Category', 'gratis-ai-agent'),
          value: categoryFilter,
          options: categoryOptions,
          onChange: setCategoryFilter,
          __nextHasNoMarginBottom: true
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
          className: "gratis-ai-agent-abilities-expand-buttons",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            variant: "tertiary",
            size: "small",
            onClick: handleExpandAll,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Expand all', 'gratis-ai-agent')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            variant: "tertiary",
            size: "small",
            onClick: handleCollapseAll,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Collapse all', 'gratis-ai-agent')
          })]
        })]
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
      className: "gratis-ai-agent-abilities-count description",
      children: filtered.length === abilities.length ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)(/* translators: %d: total number of abilities */
      (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('%d abilities', 'gratis-ai-agent'), abilities.length) : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)(/* translators: 1: filtered count, 2: total count */
      (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Showing %1$d of %2$d abilities', 'gratis-ai-agent'), filtered.length, abilities.length)
    }), filtered.length === 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
      className: "description",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No abilities match your search.', 'gratis-ai-agent')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
      className: "gratis-ai-agent-abilities-sections",
      children: grouped.map(([category, categoryAbilities]) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(AbilityCategorySection, {
        category: category,
        abilities: categoryAbilities,
        toolPermissions: toolPermissions,
        onPermChange: onPermChange,
        isFiltering: Boolean(isFiltering),
        defaultOpen: isFiltering || (openOverrides[category] !== undefined ? openOverrides[category] : allOpen)
      }, category))
    })]
  });
}

/***/ },

/***/ "./src/settings-page/agent-builder.js"
/*!********************************************!*\
  !*** ./src/settings-page/agent-builder.js ***!
  \********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ AgentBuilder)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/pencil.mjs");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/plus.mjs");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/trash.mjs");
/* harmony import */ var _store__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../store */ "./src/store/index.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__);
/**
 * WordPress dependencies
 */






/**
 * Internal dependencies
 */


const EMPTY_FORM = {
  slug: '',
  name: '',
  description: '',
  system_prompt: '',
  provider_id: '',
  model_id: '',
  temperature: '',
  max_iterations: '',
  greeting: '',
  avatar_icon: ''
};

/**
 *
 */
function AgentBuilder() {
  const {
    fetchAgents,
    createAgent,
    updateAgent,
    deleteAgent,
    fetchProviders
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useDispatch)(_store__WEBPACK_IMPORTED_MODULE_7__["default"]);
  const {
    agents,
    agentsLoaded,
    providers
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => ({
    agents: select(_store__WEBPACK_IMPORTED_MODULE_7__["default"]).getAgents(),
    agentsLoaded: select(_store__WEBPACK_IMPORTED_MODULE_7__["default"]).getAgentsLoaded(),
    providers: select(_store__WEBPACK_IMPORTED_MODULE_7__["default"]).getProviders()
  }), []);
  const [showForm, setShowForm] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [editId, setEditId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [form, setForm] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({
    ...EMPTY_FORM
  });
  const [saving, setSaving] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [notice, setNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    fetchAgents();
    fetchProviders();
  }, [fetchAgents, fetchProviders]);
  const resetForm = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setShowForm(false);
    setEditId(null);
    setForm({
      ...EMPTY_FORM
    });
    setNotice(null);
  }, []);
  const updateField = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)((key, value) => {
    setForm(prev => ({
      ...prev,
      [key]: value
    }));
  }, []);
  const handleEdit = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(agent => {
    setEditId(agent.id);
    setForm({
      slug: agent.slug || '',
      name: agent.name || '',
      description: agent.description || '',
      system_prompt: agent.system_prompt || '',
      provider_id: agent.provider_id || '',
      model_id: agent.model_id || '',
      temperature: null !== agent.temperature ? String(agent.temperature) : '',
      max_iterations: null !== agent.max_iterations ? String(agent.max_iterations) : '',
      greeting: agent.greeting || '',
      avatar_icon: agent.avatar_icon || ''
    });
    setShowForm(true);
    setNotice(null);
  }, []);
  const handleSubmit = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    if (!form.name.trim()) {
      setNotice({
        status: 'error',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Agent name is required.', 'gratis-ai-agent')
      });
      return;
    }
    if (!editId && !form.slug.trim()) {
      setNotice({
        status: 'error',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Agent slug is required.', 'gratis-ai-agent')
      });
      return;
    }
    setSaving(true);
    setNotice(null);
    try {
      const payload = {
        name: form.name,
        description: form.description,
        system_prompt: form.system_prompt,
        provider_id: form.provider_id,
        model_id: form.model_id,
        greeting: form.greeting,
        avatar_icon: form.avatar_icon
      };
      if (form.temperature !== '') {
        payload.temperature = parseFloat(form.temperature);
      }
      if (form.max_iterations !== '') {
        payload.max_iterations = parseInt(form.max_iterations, 10);
      }
      if (editId) {
        await updateAgent(editId, payload);
        setNotice({
          status: 'success',
          message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Agent updated.', 'gratis-ai-agent')
        });
      } else {
        payload.slug = form.slug;
        await createAgent(payload);
        setNotice({
          status: 'success',
          message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Agent created.', 'gratis-ai-agent')
        });
        resetForm();
      }
    } catch (err) {
      setNotice({
        status: 'error',
        message: err?.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Failed to save agent.', 'gratis-ai-agent')
      });
    }
    setSaving(false);
  }, [form, editId, createAgent, updateAgent, resetForm]);
  const handleDelete = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async agent => {
    if (
    // eslint-disable-next-line no-alert
    !window.confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.sprintf)(/* translators: %s: agent name */
    (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Delete agent "%s"? This cannot be undone.', 'gratis-ai-agent'), agent.name))) {
      return;
    }
    await deleteAgent(agent.id);
  }, [deleteAgent]);

  // Build provider options for the dropdown.
  const providerOptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('(use global default)', 'gratis-ai-agent'),
    value: ''
  }, ...providers.map(p => ({
    label: p.name,
    value: p.id
  }))];

  // Build model options based on selected provider.
  const selectedProvider = providers.find(p => p.id === form.provider_id);
  const modelOptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('(use global default)', 'gratis-ai-agent'),
    value: ''
  }, ...(selectedProvider?.models || []).map(m => ({
    label: m.name || m.id,
    value: m.id
  }))];
  if (!agentsLoaded) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("div", {
      className: "gratis-ai-agent-loading",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, {})
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
    className: "gratis-ai-agent-builder",
    children: [notice && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
      status: notice.status,
      isDismissible: true,
      onDismiss: () => setNotice(null),
      children: notice.message
    }), !showForm && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("p", {
        className: "description",
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Create specialized agents with custom system prompts, models, and tool profiles. Select an agent in the chat to use it.', 'gratis-ai-agent')
      }), agents.length === 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("p", {
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('No agents yet. Create your first agent below.', 'gratis-ai-agent')
      }), agents.map(agent => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Card, {
        className: "gratis-ai-agent-card",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardHeader, {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
            className: "gratis-ai-agent-card-header",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
              className: "gratis-ai-agent-card-title",
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("strong", {
                children: agent.name
              }), agent.description && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("span", {
                className: "gratis-ai-agent-card-desc",
                children: agent.description
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
              className: "gratis-ai-agent-card-actions",
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Edit agent', 'gratis-ai-agent'),
                onClick: () => handleEdit(agent),
                size: "small"
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_6__["default"],
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Delete agent', 'gratis-ai-agent'),
                onClick: () => handleDelete(agent),
                isDestructive: true,
                size: "small"
              })]
            })]
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardBody, {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
            className: "gratis-ai-agent-card-meta",
            children: [agent.provider_id && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("span", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Provider:', 'gratis-ai-agent')
              }), ' ', agent.provider_id]
            }), agent.model_id && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("span", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Model:', 'gratis-ai-agent')
              }), ' ', agent.model_id]
            }), null !== agent.temperature && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("span", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Temp:', 'gratis-ai-agent')
              }), ' ', agent.temperature]
            })]
          }), agent.system_prompt && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("p", {
            className: "gratis-ai-agent-prompt-preview",
            children: agent.system_prompt.length > 120 ? agent.system_prompt.slice(0, 120) + '…' : agent.system_prompt
          })]
        })]
      }, agent.id)), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
        variant: "secondary",
        icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__["default"],
        onClick: () => {
          setShowForm(true);
          setEditId(null);
          setForm({
            ...EMPTY_FORM
          });
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Add Agent', 'gratis-ai-agent')
      })]
    }), showForm && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
      className: "gratis-ai-agent-form",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("h3", {
        children: editId ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Edit Agent', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('New Agent', 'gratis-ai-agent')
      }), !editId && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Slug', 'gratis-ai-agent'),
        value: form.slug,
        onChange: v => updateField('slug', v),
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Unique identifier (lowercase, hyphens). Cannot be changed after creation.', 'gratis-ai-agent'),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Name', 'gratis-ai-agent'),
        value: form.name,
        onChange: v => updateField('name', v),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Description', 'gratis-ai-agent'),
        value: form.description,
        onChange: v => updateField('description', v),
        rows: 2,
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Short description shown in the agent list.', 'gratis-ai-agent')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('System Prompt', 'gratis-ai-agent'),
        value: form.system_prompt,
        onChange: v => updateField('system_prompt', v),
        rows: 8,
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Custom instructions for this agent. Replaces the global system prompt. Leave empty to use the global default.', 'gratis-ai-agent')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Greeting Message', 'gratis-ai-agent'),
        value: form.greeting,
        onChange: v => updateField('greeting', v),
        rows: 2,
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Message shown when this agent starts a conversation. Leave empty for the global default.', 'gratis-ai-agent')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Provider', 'gratis-ai-agent'),
        value: form.provider_id,
        options: providerOptions,
        onChange: v => {
          updateField('provider_id', v);
          updateField('model_id', '');
        },
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Override the AI provider for this agent.', 'gratis-ai-agent'),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Model', 'gratis-ai-agent'),
        value: form.model_id,
        options: modelOptions,
        onChange: v => updateField('model_id', v),
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Override the AI model for this agent.', 'gratis-ai-agent'),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Temperature', 'gratis-ai-agent'),
        type: "number",
        min: 0,
        max: 2,
        step: 0.1,
        value: form.temperature,
        onChange: v => updateField('temperature', v),
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Override temperature (0–2). Leave empty to use the global default.', 'gratis-ai-agent'),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Max Iterations', 'gratis-ai-agent'),
        type: "number",
        min: 1,
        max: 50,
        value: form.max_iterations,
        onChange: v => updateField('max_iterations', v),
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Override max tool-call iterations. Leave empty to use the global default.', 'gratis-ai-agent'),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Avatar Icon', 'gratis-ai-agent'),
        value: form.avatar_icon,
        onChange: v => updateField('avatar_icon', v),
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Dashicon name or emoji for the agent avatar (e.g. "dashicons-admin-users" or "🤖").', 'gratis-ai-agent'),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
        className: "gratis-ai-agent-form-actions",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          variant: "primary",
          onClick: handleSubmit,
          isBusy: saving,
          disabled: saving,
          children: editId ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Update Agent', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Create Agent', 'gratis-ai-agent')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          variant: "tertiary",
          onClick: resetForm,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Cancel', 'gratis-ai-agent')
        })]
      })]
    })]
  });
}

/***/ },

/***/ "./src/settings-page/automations-manager.js"
/*!**************************************************!*\
  !*** ./src/settings-page/automations-manager.js ***!
  \**************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ AutomationsManager)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/pencil.mjs");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/plus.mjs");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/trash.mjs");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__);
/**
 * WordPress dependencies
 */






const CHANNEL_TYPE_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Slack', 'gratis-ai-agent'),
  value: 'slack'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Discord', 'gratis-ai-agent'),
  value: 'discord'
}];

/**
 *
 */
function emptyChannel() {
  return {
    type: 'slack',
    webhook_url: '',
    enabled: true
  };
}
const SCHEDULE_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Hourly', 'gratis-ai-agent'),
  value: 'hourly'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Twice Daily', 'gratis-ai-agent'),
  value: 'twicedaily'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Daily', 'gratis-ai-agent'),
  value: 'daily'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Weekly', 'gratis-ai-agent'),
  value: 'weekly'
}];

/**
 *
 */
function emptyForm() {
  return {
    name: '',
    description: '',
    prompt: '',
    schedule: 'daily',
    max_iterations: 10,
    enabled: true,
    notification_channels: []
  };
}

/**
 *
 */
function AutomationsManager() {
  const [automations, setAutomations] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [loaded, setLoaded] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [templates, setTemplates] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [showForm, setShowForm] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [editId, setEditId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [form, setForm] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(emptyForm());
  const [logs, setLogs] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [viewLogsId, setViewLogsId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [running, setRunning] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [notice, setNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [testingChannel, setTestingChannel] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const fetchAll = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    try {
      const [result, tpl] = await Promise.all([_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default()({
        path: '/gratis-ai-agent/v1/automations'
      }), _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default()({
        path: '/gratis-ai-agent/v1/automation-templates'
      })]);
      setAutomations(result);
      setTemplates(tpl);
    } catch {
      setAutomations([]);
    }
    setLoaded(true);
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    fetchAll();
  }, [fetchAll]);
  const resetForm = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setShowForm(false);
    setEditId(null);
    setForm(emptyForm());
  }, []);
  const updateForm = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)((key, value) => {
    setForm(prev => ({
      ...prev,
      [key]: value
    }));
  }, []);
  const handleSubmit = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    if (!form.name.trim() || !form.prompt.trim()) {
      return;
    }
    setNotice(null);
    try {
      if (editId) {
        await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default()({
          path: `/gratis-ai-agent/v1/automations/${editId}`,
          method: 'PATCH',
          data: form
        });
      } else {
        await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default()({
          path: '/gratis-ai-agent/v1/automations',
          method: 'POST',
          data: form
        });
      }
      resetForm();
      fetchAll();
      setNotice({
        status: 'success',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Automation saved.', 'gratis-ai-agent')
      });
    } catch (err) {
      setNotice({
        status: 'error',
        message: err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to save.', 'gratis-ai-agent')
      });
    }
  }, [form, editId, resetForm, fetchAll]);
  const handleEdit = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(auto => {
    setEditId(auto.id);
    setForm({
      name: auto.name,
      description: auto.description || '',
      prompt: auto.prompt,
      schedule: auto.schedule,
      max_iterations: auto.max_iterations || 10,
      enabled: auto.enabled,
      notification_channels: auto.notification_channels || []
    });
    setShowForm(true);
  }, []);
  const addChannel = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setForm(prev => ({
      ...prev,
      notification_channels: [...(prev.notification_channels || []), emptyChannel()]
    }));
  }, []);
  const removeChannel = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(idx => {
    setForm(prev => {
      const channels = [...(prev.notification_channels || [])];
      channels.splice(idx, 1);
      return {
        ...prev,
        notification_channels: channels
      };
    });
  }, []);
  const updateChannel = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)((idx, key, value) => {
    setForm(prev => {
      const channels = [...(prev.notification_channels || [])];
      channels[idx] = {
        ...channels[idx],
        [key]: value
      };
      return {
        ...prev,
        notification_channels: channels
      };
    });
  }, []);
  const handleTestChannel = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async idx => {
    const channel = form.notification_channels[idx];
    if (!channel?.webhook_url) {
      return;
    }
    setTestingChannel(idx);
    try {
      const result = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default()({
        path: '/gratis-ai-agent/v1/automations/test-notification',
        method: 'POST',
        data: {
          type: channel.type,
          webhook_url: channel.webhook_url
        }
      });
      setNotice({
        status: result.success ? 'success' : 'error',
        message: result.message
      });
    } catch (err) {
      setNotice({
        status: 'error',
        message: err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Test failed.', 'gratis-ai-agent')
      });
    }
    setTestingChannel(null);
  }, [form.notification_channels]);
  const handleDelete = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async id => {
    if (
    // eslint-disable-next-line no-alert
    window.confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Delete this automation?', 'gratis-ai-agent'))) {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default()({
        path: `/gratis-ai-agent/v1/automations/${id}`,
        method: 'DELETE'
      });
      fetchAll();
    }
  }, [fetchAll]);
  const handleToggle = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async auto => {
    await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default()({
      path: `/gratis-ai-agent/v1/automations/${auto.id}`,
      method: 'PATCH',
      data: {
        enabled: !auto.enabled
      }
    });
    fetchAll();
  }, [fetchAll]);
  const handleRun = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async id => {
    setRunning(id);
    setNotice(null);
    try {
      const result = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default()({
        path: `/gratis-ai-agent/v1/automations/${id}/run`,
        method: 'POST'
      });
      setNotice({
        status: result.success ? 'success' : 'warning',
        message: result.success ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Automation ran successfully.', 'gratis-ai-agent') : result.error || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Automation completed with errors.', 'gratis-ai-agent')
      });
      fetchAll();
    } catch (err) {
      setNotice({
        status: 'error',
        message: err.message
      });
    }
    setRunning(null);
  }, [fetchAll]);
  const handleViewLogs = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async id => {
    if (viewLogsId === id) {
      setViewLogsId(null);
      setLogs([]);
      return;
    }
    try {
      const result = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default()({
        path: `/gratis-ai-agent/v1/automations/${id}/logs`
      });
      setLogs(result);
      setViewLogsId(id);
    } catch {
      setLogs([]);
    }
  }, [viewLogsId]);
  const handleUseTemplate = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(tpl => {
    setForm({
      ...emptyForm(),
      name: tpl.name,
      description: tpl.description || '',
      prompt: tpl.prompt,
      schedule: tpl.schedule,
      notification_channels: []
    });
    setShowForm(true);
    setEditId(null);
  }, []);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
    className: "gratis-ai-agent-automations-manager",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
      className: "gratis-ai-agent-skill-header",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("h3", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Scheduled Automations', 'gratis-ai-agent')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
          className: "description",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Cron-based AI tasks that run on a schedule.', 'gratis-ai-agent')
        })]
      }), !showForm && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
        variant: "secondary",
        icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
        onClick: () => {
          resetForm();
          setShowForm(true);
        },
        size: "compact",
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Add Automation', 'gratis-ai-agent')
      })]
    }), notice && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
      status: notice.status,
      isDismissible: true,
      onDismiss: () => setNotice(null),
      children: notice.message
    }), !showForm && templates.length > 0 && automations.length === 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
      style: {
        marginBottom: '16px'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("h4", {
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Quick Start Templates', 'gratis-ai-agent')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
        className: "gratis-ai-agent-skill-cards",
        children: templates.map((tpl, idx) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
          className: "gratis-ai-agent-skill-card",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
            className: "gratis-ai-agent-skill-card-header",
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
              className: "gratis-ai-agent-skill-card-title",
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
                children: tpl.name
              })
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
            className: "gratis-ai-agent-skill-card-description",
            children: tpl.description
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
            className: "gratis-ai-agent-skill-card-footer",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
              className: "gratis-ai-agent-skill-word-count",
              children: tpl.schedule
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
              variant: "secondary",
              size: "compact",
              onClick: () => handleUseTemplate(tpl),
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Use Template', 'gratis-ai-agent')
            })]
          })]
        }, idx))
      })]
    }), showForm && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
      className: "gratis-ai-agent-skill-form",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Name', 'gratis-ai-agent'),
        value: form.name,
        onChange: v => updateForm('name', v),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Description', 'gratis-ai-agent'),
        value: form.description,
        onChange: v => updateForm('description', v),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextareaControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Prompt', 'gratis-ai-agent'),
        value: form.prompt,
        onChange: v => updateForm('prompt', v),
        rows: 6,
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('The instruction sent to the AI when this automation runs.', 'gratis-ai-agent')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Schedule', 'gratis-ai-agent'),
        value: form.schedule,
        options: SCHEDULE_OPTIONS,
        onChange: v => updateForm('schedule', v),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Max Iterations', 'gratis-ai-agent'),
        type: "number",
        min: 1,
        max: 50,
        value: form.max_iterations,
        onChange: v => updateForm('max_iterations', parseInt(v, 10) || 10),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.BaseControl, {
        id: "gratis-ai-agent-notification-channels",
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Notification Channels', 'gratis-ai-agent'),
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Send Slack or Discord messages after each run.', 'gratis-ai-agent'),
        __nextHasNoMarginBottom: true,
        children: [(form.notification_channels || []).map((channel, idx) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
          className: "gratis-ai-agent-notification-channel",
          style: {
            display: 'flex',
            gap: '8px',
            alignItems: 'flex-end',
            marginBottom: '8px',
            flexWrap: 'wrap'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
            label: idx === 0 ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Type', 'gratis-ai-agent') : undefined,
            value: channel.type,
            options: CHANNEL_TYPE_OPTIONS,
            onChange: v => updateChannel(idx, 'type', v),
            style: {
              minWidth: '100px'
            },
            __nextHasNoMarginBottom: true
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
            label: idx === 0 ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Webhook URL', 'gratis-ai-agent') : undefined,
            value: channel.webhook_url,
            onChange: v => updateChannel(idx, 'webhook_url', v),
            placeholder: 'slack' === channel.type ? 'https://hooks.slack.com/…' : 'https://discord.com/api/webhooks/…',
            style: {
              flex: 1,
              minWidth: '220px'
            },
            __nextHasNoMarginBottom: true
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.ToggleControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('On', 'gratis-ai-agent'),
            checked: channel.enabled,
            onChange: v => updateChannel(idx, 'enabled', v),
            __nextHasNoMarginBottom: true
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            variant: "secondary",
            size: "compact",
            onClick: () => handleTestChannel(idx),
            disabled: !channel.webhook_url || testingChannel === idx,
            children: testingChannel === idx ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Spinner, {}) : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Test', 'gratis-ai-agent')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__["default"],
            size: "compact",
            isDestructive: true,
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Remove channel', 'gratis-ai-agent'),
            onClick: () => removeChannel(idx)
          })]
        }, idx)), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "tertiary",
          icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
          size: "compact",
          onClick: addChannel,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Add Channel', 'gratis-ai-agent')
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
        className: "gratis-ai-agent-skill-form-actions",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "primary",
          onClick: handleSubmit,
          disabled: !form.name.trim() || !form.prompt.trim(),
          size: "compact",
          children: editId ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Update', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Create', 'gratis-ai-agent')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "tertiary",
          onClick: resetForm,
          size: "compact",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Cancel', 'gratis-ai-agent')
        })]
      })]
    }), !loaded && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
      className: "description",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Loading…', 'gratis-ai-agent')
    }), loaded && automations.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
      className: "gratis-ai-agent-skill-cards",
      style: {
        marginTop: '16px'
      },
      children: automations.map(auto => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
        className: `gratis-ai-agent-skill-card ${!auto.enabled ? 'gratis-ai-agent-skill-card--disabled' : ''}`,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
          className: "gratis-ai-agent-skill-card-header",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.ToggleControl, {
            checked: auto.enabled,
            onChange: () => handleToggle(auto),
            __nextHasNoMarginBottom: true
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
            className: "gratis-ai-agent-skill-card-title",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
              children: auto.name
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
              className: "gratis-ai-agent-skill-badge",
              children: auto.schedule
            }), auto.notification_channels?.filter(c => c.enabled).length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("span", {
              className: "gratis-ai-agent-skill-badge",
              title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Notifications configured', 'gratis-ai-agent'),
              children: [auto.notification_channels.filter(c => c.enabled).length, ' ', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('notification', 'gratis-ai-agent'), auto.notification_channels.filter(c => c.enabled).length > 1 ? 's' : '']
            })]
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
          className: "gratis-ai-agent-skill-card-description",
          children: auto.description || auto.prompt.slice(0, 100) + '...'
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
          className: "gratis-ai-agent-skill-card-footer",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("span", {
            className: "gratis-ai-agent-skill-word-count",
            children: [auto.run_count, ' ', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('runs', 'gratis-ai-agent'), auto.last_run_at && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.Fragment, {
              children: [' ', "\xB7", ' ', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Last:', 'gratis-ai-agent'), ' ', auto.last_run_at]
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
            className: "gratis-ai-agent-skill-card-actions",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
              variant: "secondary",
              size: "small",
              onClick: () => handleRun(auto.id),
              disabled: running === auto.id,
              children: running === auto.id ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Spinner, {}) : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Run Now', 'gratis-ai-agent')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
              variant: "tertiary",
              size: "small",
              onClick: () => handleViewLogs(auto.id),
              children: viewLogsId === auto.id ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Hide Logs', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Logs', 'gratis-ai-agent')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
              icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__["default"],
              size: "small",
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Edit', 'gratis-ai-agent'),
              onClick: () => handleEdit(auto)
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
              icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__["default"],
              size: "small",
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Delete', 'gratis-ai-agent'),
              isDestructive: true,
              onClick: () => handleDelete(auto.id)
            })]
          })]
        }), viewLogsId === auto.id && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
          className: "gratis-ai-agent-automation-logs",
          children: [logs.length === 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
            className: "description",
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No logs yet.', 'gratis-ai-agent')
          }), logs.map(log => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
            className: `gratis-ai-agent-log-entry gratis-ai-agent-log--${log.status}`,
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
              className: "gratis-ai-agent-log-meta",
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                className: `gratis-ai-agent-log-status gratis-ai-agent-log-status--${log.status}`,
                children: log.status
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                children: log.created_at
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("span", {
                children: [log.duration_ms, "ms"]
              }), log.prompt_tokens > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("span", {
                children: [log.prompt_tokens + log.completion_tokens, ' ', "tokens"]
              })]
            }), log.error_message && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
              className: "gratis-ai-agent-log-error",
              children: log.error_message
            }), log.reply && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("details", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("summary", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Response', 'gratis-ai-agent')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("pre", {
                className: "gratis-ai-agent-log-reply",
                children: log.reply
              })]
            })]
          }, log.id))]
        })]
      }, auto.id))
    })]
  });
}

/***/ },

/***/ "./src/settings-page/branding-manager.js"
/*!***********************************************!*\
  !*** ./src/settings-page/branding-manager.js ***!
  \***********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ BrandingManager)
/* harmony export */ });
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);
/**
 * White-label branding settings panel (t075).
 *
 * Allows site owners to customise the AI agent's display name, greeting
 * message, brand colours, and logo/avatar URL. All values are stored in
 * the gratis_ai_agent_settings WordPress option and applied at runtime in
 * the floating widget via CSS custom properties and React props.
 */

/**
 * WordPress dependencies
 */




/**
 * Live preview of the FAB button, title bar, and greeting using the current branding values.
 *
 * @param {Object} props
 * @param {string} props.agentName       Display name shown in the title bar.
 * @param {string} props.primaryColor    Background colour for the FAB and title bar.
 * @param {string} props.textColor       Text/icon colour for the FAB and title bar.
 * @param {string} props.logoUrl         Optional logo/avatar URL shown in the FAB.
 * @param {string} props.greetingMessage Custom greeting shown in the empty chat state.
 * @return {JSX.Element} Preview element.
 */

function BrandingPreview({
  agentName,
  primaryColor,
  textColor,
  logoUrl,
  greetingMessage
}) {
  const fabBg = primaryColor || 'var(--wp-admin-theme-color, #2271b1)';
  const fabColor = textColor || '#ffffff';
  const displayName = agentName || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('AI Agent', 'gratis-ai-agent');
  const greeting = greetingMessage || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Send a message to start a conversation.', 'gratis-ai-agent');
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
    className: "gratis-ai-agent-branding-preview",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
      className: "description",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Live preview', 'gratis-ai-agent')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
      className: "gratis-ai-agent-branding-preview__fab",
      style: {
        background: fabBg,
        color: fabColor
      },
      "aria-hidden": "true",
      children: logoUrl ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("img", {
        src: logoUrl,
        alt: "",
        className: "gratis-ai-agent-branding-preview__logo"
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("svg", {
        width: "24",
        height: "24",
        viewBox: "0 0 24 24",
        fill: "currentColor",
        "aria-hidden": "true",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("path", {
          d: "M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"
        })
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
      className: "gratis-ai-agent-branding-preview__titlebar",
      style: {
        background: fabBg,
        color: fabColor
      },
      "aria-hidden": "true",
      children: [logoUrl && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("img", {
        src: logoUrl,
        alt: "",
        className: "gratis-ai-agent-branding-preview__titlebar-logo"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("span", {
        children: displayName
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
      className: "gratis-ai-agent-branding-preview__greeting",
      children: greeting
    })]
  });
}

/**
 * Branding settings panel.
 *
 * Receives the current local settings object and an `updateField` callback
 * (same pattern used by the parent SettingsApp).
 *
 * @param {Object}   props
 * @param {Object}   props.local       Current (unsaved) settings state.
 * @param {Function} props.updateField Callback to update a single settings key.
 * @return {JSX.Element} The branding settings panel.
 */
function BrandingManager({
  local,
  updateField
}) {
  const [showPrimaryPicker, setShowPrimaryPicker] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [showTextPicker, setShowTextPicker] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
    className: "gratis-ai-agent-branding-manager",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
      className: "description",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Customise how the AI agent appears to users. Leave fields empty to use the plugin defaults.', 'gratis-ai-agent')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.TextControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Agent Display Name', 'gratis-ai-agent'),
      value: local.agent_name || '',
      onChange: v => updateField('agent_name', v),
      placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('AI Agent', 'gratis-ai-agent'),
      help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Name shown in the chat title bar and floating button tooltip. Defaults to "AI Agent".', 'gratis-ai-agent'),
      __nextHasNoMarginBottom: true
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.BaseControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Primary Brand Color', 'gratis-ai-agent'),
      help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Background colour for the FAB button and chat title bar. Leave empty to use the WordPress admin theme colour.', 'gratis-ai-agent'),
      id: "gratis-ai-agent-brand-primary-color",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
        className: "gratis-ai-agent-color-field",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
          className: "gratis-ai-agent-color-swatch",
          style: {
            background: local.brand_primary_color || 'var(--wp-admin-theme-color, #2271b1)'
          },
          "aria-hidden": "true"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.TextControl, {
          id: "gratis-ai-agent-brand-primary-color",
          value: local.brand_primary_color || '',
          onChange: v => updateField('brand_primary_color', v),
          placeholder: "#2271b1",
          __nextHasNoMarginBottom: true
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Button, {
          variant: "secondary",
          size: "small",
          onClick: () => {
            setShowPrimaryPicker(v => !v);
            setShowTextPicker(false);
          },
          children: showPrimaryPicker ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Close', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Pick', 'gratis-ai-agent')
        })]
      }), showPrimaryPicker && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.ColorPicker, {
        color: local.brand_primary_color || '#2271b1',
        onChange: v => updateField('brand_primary_color', v),
        enableAlpha: false
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.BaseControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Text & Icon Color', 'gratis-ai-agent'),
      help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Colour for text and icons inside the FAB button and title bar. Defaults to white (#ffffff).', 'gratis-ai-agent'),
      id: "gratis-ai-agent-brand-text-color",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
        className: "gratis-ai-agent-color-field",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
          className: "gratis-ai-agent-color-swatch",
          style: {
            background: local.brand_text_color || '#ffffff',
            border: '1px solid #c3c4c7'
          },
          "aria-hidden": "true"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.TextControl, {
          id: "gratis-ai-agent-brand-text-color",
          value: local.brand_text_color || '',
          onChange: v => updateField('brand_text_color', v),
          placeholder: "#ffffff",
          __nextHasNoMarginBottom: true
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Button, {
          variant: "secondary",
          size: "small",
          onClick: () => {
            setShowTextPicker(v => !v);
            setShowPrimaryPicker(false);
          },
          children: showTextPicker ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Close', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Pick', 'gratis-ai-agent')
        })]
      }), showTextPicker && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.ColorPicker, {
        color: local.brand_text_color || '#ffffff',
        onChange: v => updateField('brand_text_color', v),
        enableAlpha: false
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.TextControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Logo / Avatar URL', 'gratis-ai-agent'),
      value: local.brand_logo_url || '',
      onChange: v => updateField('brand_logo_url', v),
      placeholder: "https://example.com/logo.png",
      help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('URL of an image to display inside the FAB button and title bar instead of the default chat icon. Recommended size: 24×24 px.', 'gratis-ai-agent'),
      __nextHasNoMarginBottom: true
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.TextareaControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Greeting Message', 'gratis-ai-agent'),
      value: local.greeting_message || '',
      onChange: v => updateField('greeting_message', v),
      placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Send a message to start a conversation.', 'gratis-ai-agent'),
      help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Text shown in the chat before the first message. Leave empty to use the default.', 'gratis-ai-agent'),
      rows: 2
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(BrandingPreview, {
      agentName: local.agent_name,
      primaryColor: local.brand_primary_color,
      textColor: local.brand_text_color,
      logoUrl: local.brand_logo_url,
      greetingMessage: local.greeting_message
    })]
  });
}

/***/ },

/***/ "./src/settings-page/custom-tools-manager.js"
/*!***************************************************!*\
  !*** ./src/settings-page/custom-tools-manager.js ***!
  \***************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ CustomToolsManager)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/pencil.mjs");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/plus.mjs");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/seen.mjs");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/trash.mjs");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_7__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__);
/**
 * WordPress dependencies
 */






const TOOL_TYPES = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('HTTP Request', 'gratis-ai-agent'),
  value: 'http'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('WordPress Action', 'gratis-ai-agent'),
  value: 'action'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('WP-CLI Command', 'gratis-ai-agent'),
  value: 'cli'
}];
const HTTP_METHODS = [{
  label: 'GET',
  value: 'GET'
}, {
  label: 'POST',
  value: 'POST'
}, {
  label: 'PUT',
  value: 'PUT'
}, {
  label: 'PATCH',
  value: 'PATCH'
}, {
  label: 'DELETE',
  value: 'DELETE'
}];

/**
 *
 */
function emptyForm() {
  return {
    slug: '',
    name: '',
    description: '',
    type: 'http',
    enabled: true,
    config: {
      method: 'GET',
      url: '',
      headers: '{}',
      body: ''
    },
    input_schema: '{}'
  };
}

/**
 *
 */
function CustomToolsManager() {
  const [tools, setTools] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [loaded, setLoaded] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [showForm, setShowForm] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [editId, setEditId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [form, setForm] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(emptyForm());
  const [testResult, setTestResult] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [notice, setNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const fetchTools = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    try {
      const result = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_7___default()({
        path: '/gratis-ai-agent/v1/custom-tools'
      });
      setTools(result);
    } catch {
      setTools([]);
    }
    setLoaded(true);
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    fetchTools();
  }, [fetchTools]);
  const resetForm = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setShowForm(false);
    setEditId(null);
    setForm(emptyForm());
    setTestResult(null);
  }, []);
  const updateForm = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)((key, value) => {
    setForm(prev => ({
      ...prev,
      [key]: value
    }));
  }, []);
  const updateConfig = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)((key, value) => {
    setForm(prev => ({
      ...prev,
      config: {
        ...prev.config,
        [key]: value
      }
    }));
  }, []);
  const handleSubmit = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    if (!form.name.trim()) {
      return;
    }
    setNotice(null);
    try {
      const data = {
        ...form,
        config: typeof form.config === 'string' ? JSON.parse(form.config) : form.config,
        input_schema: typeof form.input_schema === 'string' ? JSON.parse(form.input_schema) : form.input_schema
      };
      if (editId) {
        await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_7___default()({
          path: `/gratis-ai-agent/v1/custom-tools/${editId}`,
          method: 'PATCH',
          data
        });
      } else {
        await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_7___default()({
          path: '/gratis-ai-agent/v1/custom-tools',
          method: 'POST',
          data
        });
      }
      resetForm();
      fetchTools();
      setNotice({
        status: 'success',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Tool saved.', 'gratis-ai-agent')
      });
    } catch (err) {
      setNotice({
        status: 'error',
        message: err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to save tool.', 'gratis-ai-agent')
      });
    }
  }, [form, editId, resetForm, fetchTools]);
  const handleEdit = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(tool => {
    setEditId(tool.id);
    setForm({
      slug: tool.slug,
      name: tool.name,
      description: tool.description,
      type: tool.type,
      enabled: tool.enabled,
      config: tool.config || {},
      input_schema: JSON.stringify(tool.input_schema || {}, null, 2)
    });
    setShowForm(true);
    setTestResult(null);
  }, []);
  const handleDelete = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async id => {
    if (
    // eslint-disable-next-line no-alert
    window.confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Delete this custom tool?', 'gratis-ai-agent'))) {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_7___default()({
        path: `/gratis-ai-agent/v1/custom-tools/${id}`,
        method: 'DELETE'
      });
      fetchTools();
    }
  }, [fetchTools]);
  const handleToggle = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async tool => {
    await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_7___default()({
      path: `/gratis-ai-agent/v1/custom-tools/${tool.id}`,
      method: 'PATCH',
      data: {
        enabled: !tool.enabled
      }
    });
    fetchTools();
  }, [fetchTools]);
  const handleTest = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    if (!editId) {
      return;
    }
    setTestResult(null);
    try {
      const result = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_7___default()({
        path: `/gratis-ai-agent/v1/custom-tools/${editId}/test`,
        method: 'POST',
        data: {
          args: {}
        }
      });
      setTestResult(result);
    } catch (err) {
      setTestResult({
        success: false,
        output: err.message
      });
    }
  }, [editId]);
  const renderConfigFields = () => {
    const cfg = form.config || {};
    switch (form.type) {
      case 'http':
        return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.Fragment, {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('HTTP Method', 'gratis-ai-agent'),
            value: cfg.method || 'GET',
            options: HTTP_METHODS,
            onChange: v => updateConfig('method', v),
            __nextHasNoMarginBottom: true
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('URL', 'gratis-ai-agent'),
            value: cfg.url || '',
            onChange: v => updateConfig('url', v),
            placeholder: "https://api.example.com/endpoint?q={{query}}",
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Use {{param}} placeholders for dynamic values.', 'gratis-ai-agent'),
            __nextHasNoMarginBottom: true
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextareaControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Headers (JSON)', 'gratis-ai-agent'),
            value: typeof cfg.headers === 'object' ? JSON.stringify(cfg.headers, null, 2) : cfg.headers || '{}',
            onChange: v => updateConfig('headers', v),
            rows: 3
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextareaControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Body Template', 'gratis-ai-agent'),
            value: cfg.body || '',
            onChange: v => updateConfig('body', v),
            rows: 3,
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Use {{param}} placeholders. Leave empty for GET requests.', 'gratis-ai-agent')
          })]
        });
      case 'action':
        return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.Fragment, {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Hook Name', 'gratis-ai-agent'),
            value: cfg.hook_name || '',
            onChange: v => updateConfig('hook_name', v),
            placeholder: "my_custom_action",
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('The WordPress action hook to call via do_action().', 'gratis-ai-agent'),
            __nextHasNoMarginBottom: true
          })
        });
      case 'cli':
        return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.Fragment, {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('WP-CLI Command', 'gratis-ai-agent'),
            value: cfg.command || '',
            onChange: v => updateConfig('command', v),
            placeholder: "cache flush",
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Command to run (without the "wp" prefix). Use {{param}} placeholders.', 'gratis-ai-agent'),
            __nextHasNoMarginBottom: true
          })
        });
      default:
        return null;
    }
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
    className: "gratis-ai-agent-custom-tools-manager",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
      className: "gratis-ai-agent-skill-header",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("h3", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Custom Tools', 'gratis-ai-agent')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("p", {
          className: "description",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Create custom tools that the AI can use — HTTP APIs, WordPress actions, or WP-CLI commands.', 'gratis-ai-agent')
        })]
      }), !showForm && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
        variant: "secondary",
        icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
        onClick: () => setShowForm(true),
        size: "compact",
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Add Tool', 'gratis-ai-agent')
      })]
    }), notice && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
      status: notice.status,
      isDismissible: true,
      onDismiss: () => setNotice(null),
      children: notice.message
    }), showForm && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
      className: "gratis-ai-agent-skill-form",
      children: [!editId && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Slug', 'gratis-ai-agent'),
        value: form.slug,
        onChange: v => updateForm('slug', v),
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Unique identifier (lowercase, hyphens).', 'gratis-ai-agent'),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Name', 'gratis-ai-agent'),
        value: form.name,
        onChange: v => updateForm('name', v),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Description', 'gratis-ai-agent'),
        value: form.description,
        onChange: v => updateForm('description', v),
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Explains to the AI when this tool should be used.', 'gratis-ai-agent'),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Type', 'gratis-ai-agent'),
        value: form.type,
        options: TOOL_TYPES,
        onChange: v => {
          updateForm('type', v);
          // Reset config for new type.
          if (v === 'http') {
            updateForm('config', {
              method: 'GET',
              url: '',
              headers: '{}',
              body: ''
            });
          } else if (v === 'action') {
            updateForm('config', {
              hook_name: ''
            });
          } else {
            updateForm('config', {
              command: ''
            });
          }
        },
        __nextHasNoMarginBottom: true
      }), renderConfigFields(), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextareaControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Input Schema (JSON)', 'gratis-ai-agent'),
        value: form.input_schema,
        onChange: v => updateForm('input_schema', v),
        rows: 4,
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('JSON Schema describing the parameters the AI should provide.', 'gratis-ai-agent')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
        className: "gratis-ai-agent-skill-form-actions",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "primary",
          onClick: handleSubmit,
          disabled: !form.name.trim() || !editId && !form.slug.trim(),
          size: "compact",
          children: editId ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Update', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Create', 'gratis-ai-agent')
        }), editId && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "secondary",
          icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__["default"],
          onClick: handleTest,
          size: "compact",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Test', 'gratis-ai-agent')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "tertiary",
          onClick: resetForm,
          size: "compact",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Cancel', 'gratis-ai-agent')
        })]
      }), testResult && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
        className: `gratis-ai-agent-test-result ${testResult.success ? 'is-success' : 'is-error'}`,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("strong", {
          children: testResult.success ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Success', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Error', 'gratis-ai-agent')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("pre", {
          children: typeof testResult.output === 'object' ? JSON.stringify(testResult.output, null, 2) : testResult.output
        })]
      })]
    }), !loaded && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("p", {
      className: "description",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Loading…', 'gratis-ai-agent')
    }), loaded && tools.length === 0 && !showForm && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("p", {
      className: "description",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No custom tools yet. Create one or deactivate/reactivate the plugin to seed examples.', 'gratis-ai-agent')
    }), tools.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("div", {
      className: "gratis-ai-agent-skill-cards",
      children: tools.map(tool => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
        className: `gratis-ai-agent-skill-card ${!tool.enabled ? 'gratis-ai-agent-skill-card--disabled' : ''}`,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
          className: "gratis-ai-agent-skill-card-header",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.ToggleControl, {
            checked: tool.enabled,
            onChange: () => handleToggle(tool),
            __nextHasNoMarginBottom: true
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
            className: "gratis-ai-agent-skill-card-title",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("strong", {
              children: tool.name
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("span", {
              className: "gratis-ai-agent-skill-badge",
              children: tool.type.toUpperCase()
            })]
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("p", {
          className: "gratis-ai-agent-skill-card-description",
          children: tool.description
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
          className: "gratis-ai-agent-skill-card-footer",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("span", {
            className: "gratis-ai-agent-skill-word-count",
            children: tool.slug
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
            className: "gratis-ai-agent-skill-card-actions",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
              icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__["default"],
              size: "small",
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Edit', 'gratis-ai-agent'),
              onClick: () => handleEdit(tool)
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
              icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_6__["default"],
              size: "small",
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Delete', 'gratis-ai-agent'),
              isDestructive: true,
              onClick: () => handleDelete(tool.id)
            })]
          })]
        })]
      }, tool.id))
    })]
  });
}

/***/ },

/***/ "./src/settings-page/events-manager.js"
/*!*********************************************!*\
  !*** ./src/settings-page/events-manager.js ***!
  \*********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ EventsManager)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/pencil.mjs");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/plus.mjs");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/trash.mjs");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__);
/**
 * WordPress dependencies
 */






/**
 *
 */

function emptyForm() {
  return {
    name: '',
    description: '',
    hook_name: '',
    prompt_template: '',
    conditions: '{}',
    max_iterations: 10,
    enabled: true
  };
}

/**
 *
 */
function EventsManager() {
  const [events, setEvents] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [loaded, setLoaded] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [triggers, setTriggers] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [showForm, setShowForm] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [editId, setEditId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [form, setForm] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(emptyForm());
  const [logs, setLogs] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [viewLogsId, setViewLogsId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [notice, setNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const fetchAll = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    try {
      const [result, trigs] = await Promise.all([_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default()({
        path: '/gratis-ai-agent/v1/event-automations'
      }), _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default()({
        path: '/gratis-ai-agent/v1/event-triggers'
      })]);
      setEvents(result);
      setTriggers(trigs);
    } catch {
      setEvents([]);
    }
    setLoaded(true);
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    fetchAll();
  }, [fetchAll]);
  const resetForm = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setShowForm(false);
    setEditId(null);
    setForm(emptyForm());
  }, []);
  const updateForm = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)((key, value) => {
    setForm(prev => ({
      ...prev,
      [key]: value
    }));
  }, []);
  const handleSubmit = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    if (!form.name.trim() || !form.hook_name || !form.prompt_template.trim()) {
      return;
    }
    setNotice(null);
    try {
      const data = {
        ...form,
        conditions: typeof form.conditions === 'string' ? JSON.parse(form.conditions) : form.conditions
      };
      if (editId) {
        await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default()({
          path: `/gratis-ai-agent/v1/event-automations/${editId}`,
          method: 'PATCH',
          data
        });
      } else {
        await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default()({
          path: '/gratis-ai-agent/v1/event-automations',
          method: 'POST',
          data
        });
      }
      resetForm();
      fetchAll();
      setNotice({
        status: 'success',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Event automation saved.', 'gratis-ai-agent')
      });
    } catch (err) {
      setNotice({
        status: 'error',
        message: err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to save.', 'gratis-ai-agent')
      });
    }
  }, [form, editId, resetForm, fetchAll]);
  const handleEdit = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(ev => {
    setEditId(ev.id);
    setForm({
      name: ev.name,
      description: ev.description || '',
      hook_name: ev.hook_name,
      prompt_template: ev.prompt_template,
      conditions: JSON.stringify(ev.conditions || {}, null, 2),
      max_iterations: ev.max_iterations || 10,
      enabled: ev.enabled
    });
    setShowForm(true);
  }, []);
  const handleDelete = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async id => {
    if (
    // eslint-disable-next-line no-alert
    window.confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Delete this event automation?', 'gratis-ai-agent'))) {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default()({
        path: `/gratis-ai-agent/v1/event-automations/${id}`,
        method: 'DELETE'
      });
      fetchAll();
    }
  }, [fetchAll]);
  const handleToggle = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async ev => {
    await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default()({
      path: `/gratis-ai-agent/v1/event-automations/${ev.id}`,
      method: 'PATCH',
      data: {
        enabled: !ev.enabled
      }
    });
    fetchAll();
  }, [fetchAll]);
  const handleViewLogs = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async id => {
    if (viewLogsId === id) {
      setViewLogsId(null);
      setLogs([]);
      return;
    }
    try {
      const result = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default()({
        path: '/gratis-ai-agent/v1/automation-logs?trigger_type=event&limit=20'
      });
      setLogs(result);
      setViewLogsId(id);
    } catch {
      setLogs([]);
    }
  }, [viewLogsId]);

  // Group triggers by category.
  const triggersByCategory = triggers.reduce((acc, t) => {
    if (!acc[t.category]) {
      acc[t.category] = [];
    }
    acc[t.category].push(t);
    return acc;
  }, {});
  const triggerOptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Select a trigger…', 'gratis-ai-agent'),
    value: ''
  }, ...Object.entries(triggersByCategory).flatMap(([category, items]) => [{
    label: `--- ${category.charAt(0).toUpperCase() + category.slice(1)} ---`,
    value: `__group_${category}`,
    disabled: true
  }, ...items.map(t => ({
    label: `${t.label} (${t.hook_name})`,
    value: t.hook_name
  }))])];
  const selectedTrigger = triggers.find(t => t.hook_name === form.hook_name);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
    className: "gratis-ai-agent-events-manager",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
      className: "gratis-ai-agent-skill-header",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("h3", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Event-Driven Automations', 'gratis-ai-agent')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
          className: "description",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Trigger AI actions when WordPress hooks fire — post published, user registered, order placed, etc.', 'gratis-ai-agent')
        })]
      }), !showForm && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
        variant: "secondary",
        icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
        onClick: () => {
          resetForm();
          setShowForm(true);
        },
        size: "compact",
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Add Event', 'gratis-ai-agent')
      })]
    }), notice && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
      status: notice.status,
      isDismissible: true,
      onDismiss: () => setNotice(null),
      children: notice.message
    }), showForm && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
      className: "gratis-ai-agent-skill-form",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Name', 'gratis-ai-agent'),
        value: form.name,
        onChange: v => updateForm('name', v),
        placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('e.g., "Auto-tag new posts"', 'gratis-ai-agent'),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Description', 'gratis-ai-agent'),
        value: form.description,
        onChange: v => updateForm('description', v),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Trigger Hook', 'gratis-ai-agent'),
        value: form.hook_name,
        options: triggerOptions,
        onChange: v => {
          if (v.startsWith('__group_')) {
            return;
          }
          updateForm('hook_name', v);
        },
        __nextHasNoMarginBottom: true
      }), selectedTrigger && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
        className: "gratis-ai-agent-trigger-info",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
          className: "description",
          children: selectedTrigger.description
        }), (() => {
          // The REST API returns placeholders as a
          // key→label object; normalise to an array of
          // keys so the component works with both the
          // real API and array-format mocks.
          const ph = selectedTrigger.placeholders;
          const keys = Array.isArray(ph) ? ph.map(p => p.key) : Object.keys(ph || {});
          return keys.length > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("p", {
            className: "description",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Available placeholders:', 'gratis-ai-agent')
            }), ' ', keys.map(k => `{{${k}}}`).join(', ')]
          }) : null;
        })(), (() => {
          // Same normalisation for conditions.
          const cond = selectedTrigger.conditions;
          const keys = Array.isArray(cond) ? cond.map(c => c.key) : Object.keys(cond || {});
          return keys.length > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("p", {
            className: "description",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Available conditions:', 'gratis-ai-agent')
            }), ' ', keys.join(', ')]
          }) : null;
        })()]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextareaControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Prompt Template', 'gratis-ai-agent'),
        value: form.prompt_template,
        onChange: v => updateForm('prompt_template', v),
        rows: 6,
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Use {{placeholders}} for dynamic data from the triggering event.', 'gratis-ai-agent')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextareaControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Conditions (JSON)', 'gratis-ai-agent'),
        value: form.conditions,
        onChange: v => updateForm('conditions', v),
        rows: 3,
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Optional. e.g., {"post_type":"post","new_status":"publish"}', 'gratis-ai-agent')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Max Iterations', 'gratis-ai-agent'),
        type: "number",
        min: 1,
        max: 50,
        value: form.max_iterations,
        onChange: v => updateForm('max_iterations', parseInt(v, 10) || 10),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
        className: "gratis-ai-agent-skill-form-actions",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "primary",
          onClick: handleSubmit,
          disabled: !form.name.trim() || !form.hook_name || !form.prompt_template.trim(),
          size: "compact",
          children: editId ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Update', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Create', 'gratis-ai-agent')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "tertiary",
          onClick: resetForm,
          size: "compact",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Cancel', 'gratis-ai-agent')
        })]
      })]
    }), !loaded && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
      className: "description",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Loading…', 'gratis-ai-agent')
    }), loaded && events.length === 0 && !showForm && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
      className: "description",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No event automations configured yet.', 'gratis-ai-agent')
    }), events.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
      className: "gratis-ai-agent-skill-cards",
      style: {
        marginTop: '16px'
      },
      children: events.map(ev => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
        className: `gratis-ai-agent-skill-card ${!ev.enabled ? 'gratis-ai-agent-skill-card--disabled' : ''}`,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
          className: "gratis-ai-agent-skill-card-header",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.ToggleControl, {
            checked: ev.enabled,
            onChange: () => handleToggle(ev),
            __nextHasNoMarginBottom: true
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
            className: "gratis-ai-agent-skill-card-title",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
              children: ev.name
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
              className: "gratis-ai-agent-skill-badge",
              children: ev.hook_name
            })]
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
          className: "gratis-ai-agent-skill-card-description",
          children: ev.description || ev.prompt_template.slice(0, 100) + '...'
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
          className: "gratis-ai-agent-skill-card-footer",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("span", {
            className: "gratis-ai-agent-skill-word-count",
            children: [ev.run_count, ' ', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('runs', 'gratis-ai-agent'), ev.last_run_at && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.Fragment, {
              children: [' ', "\xB7", ' ', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Last:', 'gratis-ai-agent'), ' ', ev.last_run_at]
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
            className: "gratis-ai-agent-skill-card-actions",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
              variant: "tertiary",
              size: "small",
              onClick: () => handleViewLogs(ev.id),
              children: viewLogsId === ev.id ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Hide Logs', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Logs', 'gratis-ai-agent')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
              icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__["default"],
              size: "small",
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Edit', 'gratis-ai-agent'),
              onClick: () => handleEdit(ev)
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
              icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__["default"],
              size: "small",
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Delete', 'gratis-ai-agent'),
              isDestructive: true,
              onClick: () => handleDelete(ev.id)
            })]
          })]
        }), viewLogsId === ev.id && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
          className: "gratis-ai-agent-automation-logs",
          children: [logs.length === 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
            className: "description",
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No logs yet.', 'gratis-ai-agent')
          }), logs.map(log => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
            className: `gratis-ai-agent-log-entry gratis-ai-agent-log--${log.status}`,
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
              className: "gratis-ai-agent-log-meta",
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                className: `gratis-ai-agent-log-status gratis-ai-agent-log-status--${log.status}`,
                children: log.status
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                children: log.trigger_name || log.hook_name
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                children: log.created_at
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("span", {
                children: [log.duration_ms, "ms"]
              })]
            }), log.error_message && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
              className: "gratis-ai-agent-log-error",
              children: log.error_message
            }), log.reply && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("details", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("summary", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Response', 'gratis-ai-agent')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("pre", {
                className: "gratis-ai-agent-log-reply",
                children: log.reply
              })]
            })]
          }, log.id))]
        })]
      }, ev.id))
    })]
  });
}

/***/ },

/***/ "./src/settings-page/knowledge-manager.js"
/*!************************************************!*\
  !*** ./src/settings-page/knowledge-manager.js ***!
  \************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ KnowledgeManager)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);
/**
 * WordPress dependencies
 */





const API_BASE = '/gratis-ai-agent/v1/knowledge';

/**
 *
 */
function KnowledgeManager() {
  const [collections, setCollections] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [notice, setNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [showCreate, setShowCreate] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [editingId, setEditingId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [expandedId, setExpandedId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [sources, setSources] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [indexing, setIndexing] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [uploading, setUploading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});

  // Search preview state.
  const [searchQuery, setSearchQuery] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [searchResults, setSearchResults] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [searching, setSearching] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);

  // Form state.
  const [form, setForm] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({
    name: '',
    slug: '',
    description: '',
    auto_index: false,
    source_config: {
      post_types: ['post', 'page']
    }
  });
  const fetchCollections = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setLoading(true);
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
      path: `${API_BASE}/collections`
    }).then(setCollections).catch(() => setNotice({
      status: 'error',
      message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to load collections.', 'gratis-ai-agent')
    })).finally(() => setLoading(false));
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    fetchCollections();
  }, [fetchCollections]);
  const fetchSources = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(collectionId => {
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
      path: `${API_BASE}/collections/${collectionId}/sources`
    }).then(data => {
      setSources(prev => ({
        ...prev,
        [collectionId]: data
      }));
    }).catch(() => {});
  }, []);
  const handleCreateOrEdit = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    try {
      if (editingId) {
        await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
          path: `${API_BASE}/collections/${editingId}`,
          method: 'PATCH',
          data: form
        });
        setNotice({
          status: 'success',
          message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Collection updated.', 'gratis-ai-agent')
        });
      } else {
        await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
          path: `${API_BASE}/collections`,
          method: 'POST',
          data: form
        });
        setNotice({
          status: 'success',
          message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Collection created.', 'gratis-ai-agent')
        });
      }
      setShowCreate(false);
      setEditingId(null);
      setForm({
        name: '',
        slug: '',
        description: '',
        auto_index: false,
        source_config: {
          post_types: ['post', 'page']
        }
      });
      fetchCollections();
    } catch (err) {
      setNotice({
        status: 'error',
        message: err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Operation failed.', 'gratis-ai-agent')
      });
    }
  }, [form, editingId, fetchCollections]);
  const handleDelete = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async id => {
    if (
    // eslint-disable-next-line no-alert
    !window.confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Delete this collection and all its indexed data?', 'gratis-ai-agent'))) {
      return;
    }
    try {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `${API_BASE}/collections/${id}`,
        method: 'DELETE'
      });
      setNotice({
        status: 'success',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Collection deleted.', 'gratis-ai-agent')
      });
      fetchCollections();
    } catch {
      setNotice({
        status: 'error',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to delete collection.', 'gratis-ai-agent')
      });
    }
  }, [fetchCollections]);
  const handleIndex = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async id => {
    setIndexing(prev => ({
      ...prev,
      [id]: true
    }));
    try {
      const result = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `${API_BASE}/collections/${id}/index`,
        method: 'POST'
      });
      setNotice({
        status: 'success',
        message: `${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Indexed:', 'gratis-ai-agent')} ${result.indexed} | ${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Skipped:', 'gratis-ai-agent')} ${result.skipped} | ${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Errors:', 'gratis-ai-agent')} ${result.errors}`
      });
      fetchCollections();
      if (expandedId === id) {
        fetchSources(id);
      }
    } catch {
      setNotice({
        status: 'error',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Indexing failed.', 'gratis-ai-agent')
      });
    }
    setIndexing(prev => ({
      ...prev,
      [id]: false
    }));
  }, [fetchCollections, fetchSources, expandedId]);
  const handleUpload = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async (id, event) => {
    const file = event.target?.files?.[0];
    if (!file) {
      return;
    }
    setUploading(prev => ({
      ...prev,
      [id]: true
    }));
    const formData = new FormData();
    formData.append('file', file);
    formData.append('collection_id', id);
    try {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `${API_BASE}/upload`,
        method: 'POST',
        body: formData,
        // Don't set Content-Type — let browser set it with boundary.
        headers: {}
      });
      setNotice({
        status: 'success',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Document uploaded and indexed.', 'gratis-ai-agent')
      });
      fetchCollections();
      if (expandedId === id) {
        fetchSources(id);
      }
    } catch {
      setNotice({
        status: 'error',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Upload failed.', 'gratis-ai-agent')
      });
    }
    setUploading(prev => ({
      ...prev,
      [id]: false
    }));
  }, [fetchCollections, fetchSources, expandedId]);
  const handleDeleteSource = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async (sourceId, collectionId) => {
    try {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `${API_BASE}/sources/${sourceId}`,
        method: 'DELETE'
      });
      fetchSources(collectionId);
      fetchCollections();
    } catch {
      setNotice({
        status: 'error',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to delete source.', 'gratis-ai-agent')
      });
    }
  }, [fetchSources, fetchCollections]);
  const handleSearch = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    if (!searchQuery.trim()) {
      return;
    }
    setSearching(true);
    try {
      const results = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `${API_BASE}/search?q=${encodeURIComponent(searchQuery)}`
      });
      setSearchResults(results);
    } catch {
      setSearchResults([]);
    }
    setSearching(false);
  }, [searchQuery]);
  const openEdit = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(collection => {
    setForm({
      name: collection.name,
      slug: collection.slug,
      description: collection.description || '',
      auto_index: collection.auto_index,
      source_config: collection.source_config || {
        post_types: ['post', 'page']
      }
    });
    setEditingId(collection.id);
    setShowCreate(true);
  }, []);
  const toggleExpanded = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(id => {
    if (expandedId === id) {
      setExpandedId(null);
    } else {
      setExpandedId(id);
      if (!sources[id]) {
        fetchSources(id);
      }
    }
  }, [expandedId, sources, fetchSources]);
  if (loading) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Spinner, {});
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
    className: "gratis-ai-agent-knowledge-manager",
    children: [notice && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
      status: notice.status,
      isDismissible: true,
      onDismiss: () => setNotice(null),
      children: notice.message
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      style: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: '16px'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("h3", {
        style: {
          margin: 0
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Collections', 'gratis-ai-agent')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
        variant: "primary",
        onClick: () => {
          setForm({
            name: '',
            slug: '',
            description: '',
            auto_index: false,
            source_config: {
              post_types: ['post', 'page']
            }
          });
          setEditingId(null);
          setShowCreate(true);
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Create Collection', 'gratis-ai-agent')
      })]
    }), collections.length === 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
      className: "description",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No collections yet. Create one to start indexing content.', 'gratis-ai-agent')
    }), collections.map(col => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Card, {
      style: {
        marginBottom: '12px'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardHeader, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
          style: {
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            width: '100%'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {
              children: col.name
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
              className: "gratis-ai-agent-text-muted",
              style: {
                marginLeft: '8px'
              },
              children: col.slug
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
            style: {
              display: 'flex',
              gap: '8px',
              alignItems: 'center'
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("span", {
              className: "gratis-ai-agent-text-muted",
              children: [col.chunk_count, ' ', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('chunks', 'gratis-ai-agent')]
            }), col.auto_index && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
              className: "gratis-ai-agent-badge",
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Auto', 'gratis-ai-agent')
            })]
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardBody, {
        children: [col.description && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
          className: "description",
          children: col.description
        }), col.last_indexed_at && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("p", {
          className: "description",
          children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Last indexed:', 'gratis-ai-agent'), ' ', col.last_indexed_at]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
          style: {
            display: 'flex',
            gap: '8px',
            marginTop: '8px',
            flexWrap: 'wrap'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            variant: "secondary",
            onClick: () => handleIndex(col.id),
            isBusy: indexing[col.id],
            disabled: indexing[col.id],
            children: indexing[col.id] ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Indexing…', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Index Now', 'gratis-ai-agent')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.FormFileUpload, {
            accept: ".pdf,.docx,.txt,.md,.html",
            onChange: e => handleUpload(col.id, e),
            render: ({
              openFileDialog
            }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
              variant: "secondary",
              onClick: openFileDialog,
              isBusy: uploading[col.id],
              disabled: uploading[col.id],
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Upload Document', 'gratis-ai-agent')
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            variant: "tertiary",
            onClick: () => toggleExpanded(col.id),
            children: expandedId === col.id ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Hide Sources', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Show Sources', 'gratis-ai-agent')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            variant: "tertiary",
            onClick: () => openEdit(col),
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Edit', 'gratis-ai-agent')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            variant: "tertiary",
            isDestructive: true,
            onClick: () => handleDelete(col.id),
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Delete', 'gratis-ai-agent')
          })]
        }), expandedId === col.id && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
          style: {
            marginTop: '12px',
            borderTop: '1px solid #ddd',
            paddingTop: '12px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("h4", {
            style: {
              margin: '0 0 8px 0'
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Sources', 'gratis-ai-agent')
          }), /* eslint-disable-next-line no-nested-ternary */
          !sources[col.id] ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Spinner, {}) : sources[col.id].length === 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            className: "description",
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No sources indexed yet.', 'gratis-ai-agent')
          }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("table", {
            className: "widefat striped",
            style: {
              marginTop: '4px'
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("thead", {
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("tr", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Title', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Type', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Status', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Chunks', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {})]
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("tbody", {
              children: sources[col.id].map(src => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("tr", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("td", {
                  children: src.title || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('(untitled)', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("td", {
                  children: src.source_type
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("td", {
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
                    className: `gratis-ai-agent-status-badge is-${src.status}`,
                    children: src.status
                  }), src.error_message && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
                    title: src.error_message,
                    style: {
                      cursor: 'help',
                      marginLeft: '4px'
                    },
                    children: "\u26A0"
                  })]
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("td", {
                  children: src.chunk_count
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("td", {
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
                    variant: "tertiary",
                    isDestructive: true,
                    isSmall: true,
                    onClick: () => handleDeleteSource(src.id, col.id),
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Remove', 'gratis-ai-agent')
                  })
                })]
              }, src.id))
            })]
          })]
        })]
      })]
    }, col.id)), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      style: {
        marginTop: '24px'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("h3", {
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search Preview', 'gratis-ai-agent')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        style: {
          display: 'flex',
          gap: '8px',
          marginBottom: '12px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
          value: searchQuery,
          onChange: setSearchQuery,
          placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search the knowledge base…', 'gratis-ai-agent'),
          style: {
            flex: 1
          },
          onKeyDown: e => {
            if (e.key === 'Enter') {
              handleSearch();
            }
          },
          __nextHasNoMarginBottom: true
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "secondary",
          onClick: handleSearch,
          isBusy: searching,
          disabled: searching || !searchQuery.trim(),
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search', 'gratis-ai-agent')
        })]
      }), searchResults.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
        className: "gratis-ai-agent-search-results",
        children: searchResults.map((result, i) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Card, {
          style: {
            marginBottom: '8px'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardBody, {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
              style: {
                display: 'flex',
                justifyContent: 'space-between',
                marginBottom: '4px'
              },
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("span", {
                className: "gratis-ai-agent-text-muted",
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {
                  children: result.source_title
                }), result.collection_name && ` — ${result.collection_name}`]
              }), result.score && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("span", {
                className: "gratis-ai-agent-text-muted",
                children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Score:', 'gratis-ai-agent'), ' ', result.score.toFixed(2)]
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
              style: {
                margin: 0,
                fontSize: '13px',
                whiteSpace: 'pre-wrap'
              },
              children: result.chunk_text.length > 300 ? result.chunk_text.substring(0, 300) + '...' : result.chunk_text
            })]
          })
        }, i))
      })]
    }), showCreate && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Modal, {
      title: editingId ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Edit Collection', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Create Collection', 'gratis-ai-agent'),
      onRequestClose: () => {
        setShowCreate(false);
        setEditingId(null);
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Name', 'gratis-ai-agent'),
        value: form.name,
        onChange: v => {
          setForm(prev => ({
            ...prev,
            name: v,
            // Auto-generate slug from name if creating.
            ...(!editingId ? {
              slug: v.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
            } : {})
          }));
        },
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Slug', 'gratis-ai-agent'),
        value: form.slug,
        onChange: v => setForm(prev => ({
          ...prev,
          slug: v
        })),
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Unique identifier for this collection.', 'gratis-ai-agent'),
        disabled: !!editingId,
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextareaControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Description', 'gratis-ai-agent'),
        value: form.description,
        onChange: v => setForm(prev => ({
          ...prev,
          description: v
        })),
        rows: 2
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Post Types (comma-separated)', 'gratis-ai-agent'),
        value: (form.source_config?.post_types || []).join(', '),
        onChange: v => setForm(prev => ({
          ...prev,
          source_config: {
            ...prev.source_config,
            post_types: v.split(',').map(s => s.trim()).filter(Boolean)
          }
        })),
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Post types to include when indexing (e.g., post, page, product).', 'gratis-ai-agent'),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.ToggleControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Auto-index', 'gratis-ai-agent'),
        checked: form.auto_index,
        onChange: v => setForm(prev => ({
          ...prev,
          auto_index: v
        })),
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Automatically index new and updated posts matching this collection.', 'gratis-ai-agent'),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        style: {
          marginTop: '16px',
          display: 'flex',
          gap: '8px',
          justifyContent: 'flex-end'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "tertiary",
          onClick: () => {
            setShowCreate(false);
            setEditingId(null);
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Cancel', 'gratis-ai-agent')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "primary",
          onClick: handleCreateOrEdit,
          disabled: !form.name || !form.slug,
          children: editingId ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Save', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Create', 'gratis-ai-agent')
        })]
      })]
    })]
  });
}

/***/ },

/***/ "./src/settings-page/memory-manager.js"
/*!*********************************************!*\
  !*** ./src/settings-page/memory-manager.js ***!
  \*********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ MemoryManager)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/pencil.mjs");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/plus.mjs");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/trash.mjs");
/* harmony import */ var _store__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../store */ "./src/store/index.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__);
/**
 * WordPress dependencies
 */






/**
 * Internal dependencies
 */


const CATEGORIES = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('General', 'gratis-ai-agent'),
  value: 'general'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Site Info', 'gratis-ai-agent'),
  value: 'site_info'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('User Preferences', 'gratis-ai-agent'),
  value: 'user_preferences'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Technical Notes', 'gratis-ai-agent'),
  value: 'technical_notes'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Workflows', 'gratis-ai-agent'),
  value: 'workflows'
}];

/**
 *
 */
function MemoryManager() {
  const {
    fetchMemories,
    createMemory,
    updateMemory,
    deleteMemory
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useDispatch)(_store__WEBPACK_IMPORTED_MODULE_7__["default"]);
  const {
    memories,
    memoriesLoaded
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => ({
    memories: select(_store__WEBPACK_IMPORTED_MODULE_7__["default"]).getMemories(),
    memoriesLoaded: select(_store__WEBPACK_IMPORTED_MODULE_7__["default"]).getMemoriesLoaded()
  }), []);
  const [showForm, setShowForm] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [editId, setEditId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [formCategory, setFormCategory] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('general');
  const [formContent, setFormContent] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    fetchMemories();
  }, [fetchMemories]);
  const handleSubmit = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    if (!formContent.trim()) {
      return;
    }
    if (editId) {
      await updateMemory(editId, {
        category: formCategory,
        content: formContent
      });
    } else {
      await createMemory(formCategory, formContent);
    }
    setShowForm(false);
    setEditId(null);
    setFormCategory('general');
    setFormContent('');
  }, [editId, formCategory, formContent, createMemory, updateMemory]);
  const handleEdit = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(memory => {
    setEditId(memory.id);
    setFormCategory(memory.category);
    setFormContent(memory.content);
    setShowForm(true);
  }, []);
  const handleDelete = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async id => {
    if (
    // eslint-disable-next-line no-alert
    window.confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Delete this memory?', 'gratis-ai-agent'))) {
      await deleteMemory(id);
    }
  }, [deleteMemory]);
  const handleCancel = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setShowForm(false);
    setEditId(null);
    setFormCategory('general');
    setFormContent('');
  }, []);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
    className: "gratis-ai-agent-memory-manager",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
      className: "gratis-ai-agent-memory-header",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("h3", {
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Stored Memories', 'gratis-ai-agent')
      }), !showForm && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
        variant: "secondary",
        icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__["default"],
        onClick: () => setShowForm(true),
        size: "compact",
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Add Memory', 'gratis-ai-agent')
      })]
    }), showForm && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
      className: "gratis-ai-agent-memory-form",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Category', 'gratis-ai-agent'),
        value: formCategory,
        options: CATEGORIES,
        onChange: setFormCategory,
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Content', 'gratis-ai-agent'),
        value: formContent,
        onChange: setFormContent,
        rows: 3
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
        className: "gratis-ai-agent-memory-form-actions",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          variant: "primary",
          onClick: handleSubmit,
          disabled: !formContent.trim(),
          size: "compact",
          children: editId ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Update', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Save', 'gratis-ai-agent')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          variant: "tertiary",
          onClick: handleCancel,
          size: "compact",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Cancel', 'gratis-ai-agent')
        })]
      })]
    }), !memoriesLoaded && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("p", {
      className: "description",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Loading…', 'gratis-ai-agent')
    }), memoriesLoaded && memories.length === 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("p", {
      className: "description",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('No memories stored yet. The AI will save memories as you interact, or you can add them manually.', 'gratis-ai-agent')
    }), memories.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("table", {
      className: "gratis-ai-agent-memory-table widefat striped",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("thead", {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("tr", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("th", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Category', 'gratis-ai-agent')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("th", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Content', 'gratis-ai-agent')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("th", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Actions', 'gratis-ai-agent')
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("tbody", {
        children: memories.map(memory => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("tr", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("td", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("span", {
              className: "gratis-ai-agent-memory-category",
              children: memory.category.replace(/_/g, ' ')
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("td", {
            children: memory.content
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("td", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
              className: "gratis-ai-agent-memory-actions",
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
                size: "small",
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Edit', 'gratis-ai-agent'),
                onClick: () => handleEdit(memory)
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_6__["default"],
                size: "small",
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Delete', 'gratis-ai-agent'),
                isDestructive: true,
                onClick: () => handleDelete(memory.id)
              })]
            })
          })]
        }, memory.id))
      })]
    })]
  });
}

/***/ },

/***/ "./src/settings-page/role-permissions-manager.js"
/*!*******************************************************!*\
  !*** ./src/settings-page/role-permissions-manager.js ***!
  \*******************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ RolePermissionsManager)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);
/**
 * Role Permissions Manager component.
 *
 * Allows administrators to configure which WordPress user roles can access
 * the AI chat and which specific abilities are available per role.
 */

/**
 * WordPress dependencies
 */





/**
 * A single role row showing chat access toggle and ability restrictions.
 *
 * @param {Object}   props
 * @param {string}   props.roleSlug  WordPress role slug.
 * @param {string}   props.roleLabel Human-readable role name.
 * @param {Object}   props.config    Current config for this role.
 * @param {Array}    props.abilities All registered abilities.
 * @param {Function} props.onChange  Called with (roleSlug, newConfig).
 * @return {JSX.Element} The role row element.
 */

function RoleRow({
  roleSlug,
  roleLabel,
  config,
  abilities,
  onChange
}) {
  const chatAccess = config?.chat_access ?? false;
  const allowedAbilities = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => config?.allowed_abilities ?? [], [config]);
  const allAllowed = allowedAbilities.length === 0;
  const handleChatToggle = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(value => {
    onChange(roleSlug, {
      ...(config || {}),
      chat_access: value,
      allowed_abilities: allowedAbilities
    });
  }, [roleSlug, config, allowedAbilities, onChange]);
  const handleAllAbilitiesToggle = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(value => {
    onChange(roleSlug, {
      ...(config || {}),
      chat_access: chatAccess,
      // Empty array = all abilities allowed; populated = restricted list.
      allowed_abilities: value ? [] : abilities.map(a => a.name)
    });
  }, [roleSlug, config, chatAccess, abilities, onChange]);
  const handleAbilityToggle = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)((abilityName, checked) => {
    const updated = checked ? [...allowedAbilities, abilityName] : allowedAbilities.filter(n => n !== abilityName);
    onChange(roleSlug, {
      ...(config || {}),
      chat_access: chatAccess,
      allowed_abilities: updated
    });
  }, [roleSlug, config, chatAccess, allowedAbilities, onChange]);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
    className: "gratis-ai-agent-role-row",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      className: "gratis-ai-agent-role-header",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("h3", {
        className: "gratis-ai-agent-role-name",
        children: roleLabel
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.ToggleControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Chat Access', 'gratis-ai-agent'),
        checked: chatAccess,
        onChange: handleChatToggle,
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Allow this role to use the AI chat.', 'gratis-ai-agent'),
        __nextHasNoMarginBottom: true
      })]
    }), chatAccess && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      className: "gratis-ai-agent-role-abilities",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.ToggleControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('All abilities (unrestricted)', 'gratis-ai-agent'),
        checked: allAllowed,
        onChange: handleAllAbilitiesToggle,
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('When enabled, this role can use all available abilities. Disable to select specific abilities.', 'gratis-ai-agent'),
        __nextHasNoMarginBottom: true
      }), !allAllowed && abilities.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        className: "gratis-ai-agent-ability-list",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
          className: "description",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Select which abilities this role can use:', 'gratis-ai-agent')
        }), abilities.map(ability => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CheckboxControl, {
          label: ability.label || ability.name,
          help: ability.description || '',
          checked: allowedAbilities.includes(ability.name),
          onChange: checked => handleAbilityToggle(ability.name, checked),
          __nextHasNoMarginBottom: true
        }, ability.name))]
      })]
    })]
  });
}

/**
 * Main Role Permissions Manager component.
 *
 * @return {JSX.Element} The role permissions manager element.
 */
function RolePermissionsManager() {
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [saving, setSaving] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [notice, setNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [roles, setRoles] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [permissions, setPermissions] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [alwaysAllowed, setAlwaysAllowed] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [abilities, setAbilities] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    Promise.all([_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
      path: '/gratis-ai-agent/v1/role-permissions'
    }), _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
      path: '/gratis-ai-agent/v1/role-permissions/roles'
    }), _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
      path: '/gratis-ai-agent/v1/abilities'
    }).catch(() => [])]).then(([permData, rolesData, abilitiesData]) => {
      setPermissions(permData.permissions || {});
      setAlwaysAllowed(permData.always_allowed || []);
      setRoles(rolesData || {});
      setAbilities(abilitiesData || []);
    }).catch(() => {
      setNotice({
        status: 'error',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to load role permissions.', 'gratis-ai-agent')
      });
    }).finally(() => setLoading(false));
  }, []);
  const handleRoleChange = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)((roleSlug, newConfig) => {
    setPermissions(prev => ({
      ...prev,
      [roleSlug]: newConfig
    }));
  }, []);
  const handleSave = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    setSaving(true);
    setNotice(null);
    try {
      const result = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: '/gratis-ai-agent/v1/role-permissions',
        method: 'POST',
        data: {
          permissions
        }
      });
      setPermissions(result.permissions || {});
      setNotice({
        status: 'success',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Role permissions saved.', 'gratis-ai-agent')
      });
    } catch {
      setNotice({
        status: 'error',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to save role permissions.', 'gratis-ai-agent')
      });
    }
    setSaving(false);
  }, [permissions]);
  if (loading) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      className: "gratis-ai-agent-role-permissions-loading",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Spinner, {})
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
    className: "gratis-ai-agent-role-permissions",
    children: [notice && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
      status: notice.status,
      isDismissible: true,
      onDismiss: () => setNotice(null),
      children: notice.message
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
      className: "description",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Configure which WordPress user roles can access the AI chat and which abilities are available per role. Administrators always have full access.', 'gratis-ai-agent')
    }), alwaysAllowed.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
      className: "description",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("em", {
        children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Always allowed (cannot be restricted):', 'gratis-ai-agent'), ' ', alwaysAllowed.join(', ')]
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      className: "gratis-ai-agent-role-list",
      children: Object.entries(roles).filter(([slug]) => !alwaysAllowed.includes(slug)).map(([slug, label]) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(RoleRow, {
        roleSlug: slug,
        roleLabel: label,
        config: permissions[slug],
        abilities: abilities,
        onChange: handleRoleChange
      }, slug))
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      className: "gratis-ai-agent-role-permissions-actions",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
        variant: "primary",
        onClick: handleSave,
        isBusy: saving,
        disabled: saving,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Save Permissions', 'gratis-ai-agent')
      })
    })]
  });
}

/***/ },

/***/ "./src/settings-page/settings-app.js"
/*!*******************************************!*\
  !*** ./src/settings-page/settings-app.js ***!
  \*******************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ SettingsApp)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _components_use_text_to_speech__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../components/use-text-to-speech */ "./src/components/use-text-to-speech.js");
/* harmony import */ var _style_css__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./style.css */ "./src/settings-page/style.css");
/* harmony import */ var _store__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../store */ "./src/store/index.js");
/* harmony import */ var _components_error_boundary__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../components/error-boundary */ "./src/components/error-boundary.js");
/* harmony import */ var _components_model_pricing_selector__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ../components/model-pricing-selector */ "./src/components/model-pricing-selector.js");
/* harmony import */ var _memory_manager__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ./memory-manager */ "./src/settings-page/memory-manager.js");
/* harmony import */ var _skill_manager__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ./skill-manager */ "./src/settings-page/skill-manager.js");
/* harmony import */ var _knowledge_manager__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! ./knowledge-manager */ "./src/settings-page/knowledge-manager.js");
/* harmony import */ var _usage_dashboard__WEBPACK_IMPORTED_MODULE_13__ = __webpack_require__(/*! ./usage-dashboard */ "./src/settings-page/usage-dashboard.js");
/* harmony import */ var _custom_tools_manager__WEBPACK_IMPORTED_MODULE_14__ = __webpack_require__(/*! ./custom-tools-manager */ "./src/settings-page/custom-tools-manager.js");
/* harmony import */ var _automations_manager__WEBPACK_IMPORTED_MODULE_15__ = __webpack_require__(/*! ./automations-manager */ "./src/settings-page/automations-manager.js");
/* harmony import */ var _events_manager__WEBPACK_IMPORTED_MODULE_16__ = __webpack_require__(/*! ./events-manager */ "./src/settings-page/events-manager.js");
/* harmony import */ var _role_permissions_manager__WEBPACK_IMPORTED_MODULE_17__ = __webpack_require__(/*! ./role-permissions-manager */ "./src/settings-page/role-permissions-manager.js");
/* harmony import */ var _agent_builder__WEBPACK_IMPORTED_MODULE_18__ = __webpack_require__(/*! ./agent-builder */ "./src/settings-page/agent-builder.js");
/* harmony import */ var _branding_manager__WEBPACK_IMPORTED_MODULE_19__ = __webpack_require__(/*! ./branding-manager */ "./src/settings-page/branding-manager.js");
/* harmony import */ var _abilities_manager__WEBPACK_IMPORTED_MODULE_20__ = __webpack_require__(/*! ./abilities-manager */ "./src/settings-page/abilities-manager.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__);
/**
 * WordPress dependencies
 */







/**
 * Internal dependencies
 */
















/**
 *
 */

function SettingsApp() {
  const {
    fetchSettings,
    fetchProviders,
    saveSettings,
    setTtsEnabled,
    setTtsVoiceURI,
    setTtsRate,
    setTtsPitch
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useDispatch)(_store__WEBPACK_IMPORTED_MODULE_7__["default"]);
  const {
    settings,
    settingsLoaded,
    providers,
    ttsEnabled,
    ttsVoiceURI,
    ttsRate,
    ttsPitch
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => ({
    settings: select(_store__WEBPACK_IMPORTED_MODULE_7__["default"]).getSettings(),
    settingsLoaded: select(_store__WEBPACK_IMPORTED_MODULE_7__["default"]).getSettingsLoaded(),
    providers: select(_store__WEBPACK_IMPORTED_MODULE_7__["default"]).getProviders(),
    ttsEnabled: select(_store__WEBPACK_IMPORTED_MODULE_7__["default"]).isTtsEnabled(),
    ttsVoiceURI: select(_store__WEBPACK_IMPORTED_MODULE_7__["default"]).getTtsVoiceURI(),
    ttsRate: select(_store__WEBPACK_IMPORTED_MODULE_7__["default"]).getTtsRate(),
    ttsPitch: select(_store__WEBPACK_IMPORTED_MODULE_7__["default"]).getTtsPitch()
  }), []);

  // Available TTS voices (loaded asynchronously in some browsers).
  const ttsVoices = (0,_components_use_text_to_speech__WEBPACK_IMPORTED_MODULE_5__.useAvailableVoices)();
  const [local, setLocal] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [saving, setSaving] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [notice, setNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [abilities, setAbilities] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [activeTab, setActiveTab] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('general');

  // Scroll affordance: ref to the wrapper div, state for fade indicators.
  const tabsWrapperRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
  const [hasScrollLeft, setHasScrollLeft] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [hasScrollRight, setHasScrollRight] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);

  // Tabs that manage their own save actions — hide the global Save Settings button.
  const SELF_SAVING_TABS = ['access-branding'];

  // Google Analytics integration state.
  const [gaPropertyId, setGaPropertyId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [gaServiceJson, setGaServiceJson] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [gaStatus, setGaStatus] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null); // { has_credentials, property_id, has_service_key }
  const [gaSaving, setGaSaving] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [gaNotice, setGaNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    fetchSettings();
    fetchProviders();
    // Fetch abilities list.
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
      path: '/gratis-ai-agent/v1/abilities'
    }).then(setAbilities).catch(() => {});
    // Fetch Google Analytics credential status.
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
      path: '/gratis-ai-agent/v1/settings/google-analytics'
    }).then(data => {
      setGaStatus(data);
      if (data?.property_id) {
        setGaPropertyId(data.property_id);
      }
    }).catch(() => {});
  }, [fetchSettings, fetchProviders]);
  const handleGaSave = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    setGaSaving(true);
    setGaNotice(null);
    try {
      const result = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: '/gratis-ai-agent/v1/settings/google-analytics',
        method: 'POST',
        data: {
          property_id: gaPropertyId,
          service_account_json: gaServiceJson
        }
      });
      setGaStatus({
        has_credentials: true,
        has_property_id: true,
        property_id: result.property_id,
        has_service_key: true
      });
      setGaServiceJson(''); // Clear the JSON field after saving.
      setGaNotice({
        status: 'success',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Google Analytics credentials saved.', 'gratis-ai-agent')
      });
    } catch (err) {
      setGaNotice({
        status: 'error',
        message: err?.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Failed to save Google Analytics credentials.', 'gratis-ai-agent')
      });
    }
    setGaSaving(false);
  }, [gaPropertyId, gaServiceJson]);
  const handleGaClear = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    setGaSaving(true);
    setGaNotice(null);
    try {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: '/gratis-ai-agent/v1/settings/google-analytics',
        method: 'DELETE'
      });
      setGaStatus({
        has_credentials: false,
        has_property_id: false,
        property_id: '',
        has_service_key: false
      });
      setGaPropertyId('');
      setGaServiceJson('');
      setGaNotice({
        status: 'success',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Google Analytics credentials cleared.', 'gratis-ai-agent')
      });
    } catch {
      setGaNotice({
        status: 'error',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Failed to clear Google Analytics credentials.', 'gratis-ai-agent')
      });
    }
    setGaSaving(false);
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (settings && !local) {
      setLocal({
        ...settings
      });
    }
  }, [settings, local]);

  // Attach scroll affordance listener to the tab bar.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    const wrapper = tabsWrapperRef.current;
    if (!wrapper) {
      return;
    }
    const tabBar = wrapper.querySelector('.components-tab-panel__tabs');
    if (!tabBar) {
      return;
    }
    const updateIndicators = () => {
      const {
        scrollLeft,
        scrollWidth,
        clientWidth
      } = tabBar;
      setHasScrollLeft(scrollLeft > 0);
      setHasScrollRight(scrollLeft + clientWidth < scrollWidth - 1);
    };

    // Initial check.
    updateIndicators();
    tabBar.addEventListener('scroll', updateIndicators, {
      passive: true
    });

    // Re-check on resize (e.g. window resize changes available width).
    const resizeObserver = new ResizeObserver(updateIndicators);
    resizeObserver.observe(tabBar);
    return () => {
      tabBar.removeEventListener('scroll', updateIndicators);
      resizeObserver.disconnect();
    };
  });

  // Scroll the active tab into view whenever activeTab changes.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    const wrapper = tabsWrapperRef.current;
    if (!wrapper) {
      return;
    }
    const activeButton = wrapper.querySelector(`.components-tab-panel__tabs-item.is-active`);
    if (activeButton) {
      activeButton.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest',
        inline: 'nearest'
      });
    }
  }, [activeTab]);
  const updateField = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)((key, value) => {
    setLocal(prev => ({
      ...prev,
      [key]: value
    }));
  }, []);
  const handleSave = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    setSaving(true);
    setNotice(null);
    try {
      await saveSettings(local);
      setNotice({
        status: 'success',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Settings saved.', 'gratis-ai-agent')
      });
    } catch {
      setNotice({
        status: 'error',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Failed to save settings.', 'gratis-ai-agent')
      });
    }
    setSaving(false);
  }, [local, saveSettings]);
  if (!settingsLoaded || !local) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("div", {
      className: "gratis-ai-agent-settings-loading",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, {})
    });
  }

  // Build provider/model options.
  const providerOptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('(default)', 'gratis-ai-agent'),
    value: ''
  }, ...providers.map(p => ({
    label: p.name,
    value: p.id
  }))];
  const selectedProvider = providers.find(p => p.id === local.default_provider);

  // Consolidated tab list. Providers are configured network-wide via the
  // WP Multisite WaaS Connectors page, so no Providers tab is rendered here.
  const tabs = [{
    name: 'general',
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('General', 'gratis-ai-agent'),
    className: 'gratis-ai-agent-settings-tab'
  }, {
    name: 'memory-knowledge',
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Memory & Knowledge', 'gratis-ai-agent'),
    className: 'gratis-ai-agent-settings-tab'
  }, {
    name: 'skills',
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Skills', 'gratis-ai-agent'),
    className: 'gratis-ai-agent-settings-tab'
  }, {
    name: 'tools',
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Tools', 'gratis-ai-agent'),
    className: 'gratis-ai-agent-settings-tab'
  }, {
    name: 'automations',
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Automations', 'gratis-ai-agent'),
    className: 'gratis-ai-agent-settings-tab'
  }, {
    name: 'agents',
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Agents', 'gratis-ai-agent'),
    className: 'gratis-ai-agent-settings-tab'
  }, {
    name: 'access-branding',
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Access & Branding', 'gratis-ai-agent'),
    className: 'gratis-ai-agent-settings-tab'
  }, {
    name: 'usage',
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Usage', 'gratis-ai-agent'),
    className: 'gratis-ai-agent-settings-tab'
  }, {
    name: 'advanced',
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Advanced', 'gratis-ai-agent'),
    className: 'gratis-ai-agent-settings-tab'
  }];
  const scrollWrapperClasses = ['gratis-ai-agent-tabs-scroll-wrapper', hasScrollLeft ? 'has-scroll-left' : '', hasScrollRight ? 'has-scroll-right' : ''].filter(Boolean).join(' ');
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("div", {
    className: "gratis-ai-agent-settings",
    children: [notice && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
      status: notice.status,
      isDismissible: true,
      onDismiss: () => setNotice(null),
      children: notice.message
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
      status: "info",
      isDismissible: false,
      className: "gratis-ai-agent-providers-link-notice",
      children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Provider API keys are configured network-wide on the Connectors page.', 'gratis-ai-agent'), ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("a", {
        href: window.gratisAiAgentData?.connectorsUrl || 'options-connectors.php',
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Open Connectors →', 'gratis-ai-agent')
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("div", {
      ref: tabsWrapperRef,
      className: scrollWrapperClasses,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TabPanel, {
        tabs: tabs,
        onSelect: setActiveTab,
        children: tab => {
          switch (tab.name) {
            case 'general':
              return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("div", {
                className: "gratis-ai-agent-settings-section",
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("h3", {
                  className: "gratis-ai-agent-settings-section-title",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Model', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("table", {
                  className: "form-table gratis-ai-agent-form-table",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tbody", {
                    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-default-provider",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Default Provider', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
                          id: "gratis-default-provider",
                          value: local.default_provider,
                          options: providerOptions,
                          onChange: v => updateField('default_provider', v),
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-default-model",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Default Model', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_components_model_pricing_selector__WEBPACK_IMPORTED_MODULE_9__["default"], {
                          id: "gratis-default-model",
                          value: local.default_model,
                          models: selectedProvider?.models || [],
                          providerName: selectedProvider?.name || '',
                          onChange: v => updateField('default_model', v)
                        })
                      })]
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-max-iterations",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Max Iterations', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
                          id: "gratis-max-iterations",
                          type: "number",
                          min: 1,
                          max: 50,
                          value: local.max_iterations,
                          onChange: v => updateField('max_iterations', parseInt(v, 10) || 10),
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Maximum tool-call iterations per request.', 'gratis-ai-agent'),
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    })]
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("h3", {
                  className: "gratis-ai-agent-settings-section-title",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Chat Behaviour', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("table", {
                  className: "form-table gratis-ai-agent-form-table",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tbody", {
                    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-greeting-message",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Greeting Message', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
                          id: "gratis-greeting-message",
                          value: local.greeting_message,
                          onChange: v => updateField('greeting_message', v),
                          placeholder: settings?._defaults?.greeting_message || '',
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Shown in the chat before the first message. Leave empty for the default.', 'gratis-ai-agent'),
                          rows: 2
                        })
                      })]
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-keyboard-shortcut",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Keyboard Shortcut', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
                          id: "gratis-keyboard-shortcut",
                          value: local.keyboard_shortcut ?? 'alt+a',
                          onChange: v => updateField('keyboard_shortcut', v),
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Shortcut to open/close the floating chat widget. Use modifier keys joined by "+", e.g. "alt+a" or "ctrl+shift+k". Leave empty to disable.', 'gratis-ai-agent'),
                          placeholder: "alt+a",
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('YOLO Mode', 'gratis-ai-agent')
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("div", {
                          className: "gratis-ai-agent-settings-yolo-section",
                          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
                            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Skip all confirmation dialogs', 'gratis-ai-agent'),
                            checked: !!local.yolo_mode,
                            onChange: v => updateField('yolo_mode', v),
                            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Destructive actions will run without prompting. Use with caution.', 'gratis-ai-agent'),
                            __nextHasNoMarginBottom: true
                          }), local.yolo_mode && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("div", {
                            className: "gratis-ai-agent-yolo-warning",
                            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Warning: YOLO mode is active. All tool confirmations are skipped automatically. Destructive operations will execute without asking.', 'gratis-ai-agent')
                          })]
                        })
                      })]
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Frontend Widget', 'gratis-ai-agent')
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
                          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Show on public-facing pages', 'gratis-ai-agent'),
                          checked: !!local.show_on_frontend,
                          onChange: v => updateField('show_on_frontend', v),
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Display the floating chat widget on public-facing pages for logged-in administrators.', 'gratis-ai-agent'),
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Token Costs', 'gratis-ai-agent')
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
                          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Show token count and estimated cost', 'gratis-ai-agent'),
                          checked: local.show_token_costs !== false,
                          onChange: v => updateField('show_token_costs', v),
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Display token count and estimated cost below the chat input and after each AI response.', 'gratis-ai-agent'),
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    })]
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("h3", {
                  className: "gratis-ai-agent-settings-section-title",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('System Prompt', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("table", {
                  className: "form-table gratis-ai-agent-form-table",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("tbody", {
                    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-system-prompt",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Custom System Prompt', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
                          id: "gratis-system-prompt",
                          value: local.system_prompt,
                          onChange: v => updateField('system_prompt', v),
                          placeholder: settings?._defaults?.system_prompt || '',
                          rows: 12,
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Leave empty to use the built-in default shown above. Memories are appended automatically.', 'gratis-ai-agent')
                        })
                      })]
                    })
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("h3", {
                  className: "gratis-ai-agent-settings-section-title",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('AI Image Generation', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("p", {
                  className: "description",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Settings for the Generate AI Image ability (DALL-E 3). Requires an OpenAI API key configured in the Providers tab.', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("table", {
                  className: "form-table gratis-ai-agent-form-table",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tbody", {
                    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-image-size",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Default Image Size', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
                          id: "gratis-image-size",
                          value: local.image_generation_size || '1024x1024',
                          options: [{
                            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Square (1024×1024)', 'gratis-ai-agent'),
                            value: '1024x1024'
                          }, {
                            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Landscape (1792×1024)', 'gratis-ai-agent'),
                            value: '1792x1024'
                          }, {
                            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Portrait (1024×1792)', 'gratis-ai-agent'),
                            value: '1024x1792'
                          }],
                          onChange: v => updateField('image_generation_size', v),
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Default dimensions for generated images. Can be overridden per request.', 'gratis-ai-agent'),
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-image-quality",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Default Image Quality', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
                          id: "gratis-image-quality",
                          value: local.image_generation_quality || 'standard',
                          options: [{
                            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Standard', 'gratis-ai-agent'),
                            value: 'standard'
                          }, {
                            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('HD (higher detail, higher cost)', 'gratis-ai-agent'),
                            value: 'hd'
                          }],
                          onChange: v => updateField('image_generation_quality', v),
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('HD produces finer details and greater consistency but costs more per image.', 'gratis-ai-agent'),
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-image-style",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Default Image Style', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
                          id: "gratis-image-style",
                          value: local.image_generation_style || 'vivid',
                          options: [{
                            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Vivid (hyper-real, dramatic)', 'gratis-ai-agent'),
                            value: 'vivid'
                          }, {
                            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Natural (subdued, realistic)', 'gratis-ai-agent'),
                            value: 'natural'
                          }],
                          onChange: v => updateField('image_generation_style', v),
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Vivid is hyper-real and dramatic; Natural is more subdued and realistic.', 'gratis-ai-agent'),
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    })]
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("h3", {
                  className: "gratis-ai-agent-settings-section-title",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Spending Limits', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("p", {
                  className: "description",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Set daily and monthly budget caps to prevent runaway API costs. Spend is estimated from the usage log.', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("table", {
                  className: "form-table gratis-ai-agent-form-table",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tbody", {
                    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-budget-daily",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Daily Budget Cap (USD)', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
                          id: "gratis-budget-daily",
                          type: "number",
                          min: 0,
                          step: 0.01,
                          value: local.budget_daily_cap ?? '',
                          onChange: v => updateField('budget_daily_cap', v === '' ? 0 : parseFloat(v) || 0),
                          placeholder: "0.00",
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Maximum estimated spend per day in USD. Set to 0 for unlimited.', 'gratis-ai-agent'),
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-budget-monthly",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Monthly Budget Cap (USD)', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
                          id: "gratis-budget-monthly",
                          type: "number",
                          min: 0,
                          step: 0.01,
                          value: local.budget_monthly_cap ?? '',
                          onChange: v => updateField('budget_monthly_cap', v === '' ? 0 : parseFloat(v) || 0),
                          placeholder: "0.00",
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Maximum estimated spend per month in USD. Set to 0 for unlimited.', 'gratis-ai-agent'),
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-budget-warning-threshold",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Warning Threshold (%)', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.RangeControl, {
                          id: "gratis-budget-warning-threshold",
                          value: local.budget_warning_threshold ?? 80,
                          onChange: v => updateField('budget_warning_threshold', v),
                          min: 50,
                          max: 99,
                          step: 1,
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Show a warning banner when spend reaches this percentage of the cap.', 'gratis-ai-agent')
                        })
                      })]
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-budget-exceeded-action",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Action When Budget Exceeded', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
                          id: "gratis-budget-exceeded-action",
                          value: local.budget_exceeded_action || 'pause',
                          options: [{
                            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Pause — block new requests', 'gratis-ai-agent'),
                            value: 'pause'
                          }, {
                            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Warn — show warning but allow', 'gratis-ai-agent'),
                            value: 'warn'
                          }],
                          onChange: v => updateField('budget_exceeded_action', v),
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('"Pause" stops all new AI requests until the period resets. "Warn" shows a banner but still allows requests.', 'gratis-ai-agent'),
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    })]
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("h3", {
                  className: "gratis-ai-agent-settings-section-title",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Text-to-Speech', 'gratis-ai-agent')
                }), !_components_use_text_to_speech__WEBPACK_IMPORTED_MODULE_5__.isTTSSupported && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("p", {
                  className: "description",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Text-to-speech is not supported in this browser.', 'gratis-ai-agent')
                }), _components_use_text_to_speech__WEBPACK_IMPORTED_MODULE_5__.isTTSSupported && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("table", {
                  className: "form-table gratis-ai-agent-form-table",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tbody", {
                    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Text-to-Speech', 'gratis-ai-agent')
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
                          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Read AI responses aloud automatically', 'gratis-ai-agent'),
                          checked: ttsEnabled,
                          onChange: setTtsEnabled,
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Use the speaker button in the chat header to toggle on the fly.', 'gratis-ai-agent'),
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    }), ttsVoices.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-tts-voice",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Voice', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
                          id: "gratis-tts-voice",
                          value: ttsVoiceURI,
                          options: [{
                            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('(Browser default)', 'gratis-ai-agent'),
                            value: ''
                          }, ...ttsVoices.map(v => ({
                            label: `${v.name} (${v.lang})`,
                            value: v.voiceURI
                          }))],
                          onChange: setTtsVoiceURI,
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Select the voice used for speech synthesis.', 'gratis-ai-agent'),
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-tts-rate",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Speech Rate', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.RangeControl, {
                          id: "gratis-tts-rate",
                          value: ttsRate,
                          onChange: setTtsRate,
                          min: 0.5,
                          max: 2,
                          step: 0.1,
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Speed of speech. 1 is normal speed.', 'gratis-ai-agent')
                        })
                      })]
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-tts-pitch",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Pitch', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.RangeControl, {
                          id: "gratis-tts-pitch",
                          value: ttsPitch,
                          onChange: setTtsPitch,
                          min: 0,
                          max: 2,
                          step: 0.1,
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Pitch of speech. 1 is normal pitch.', 'gratis-ai-agent')
                        })
                      })]
                    })]
                  })
                })]
              });
            case 'memory-knowledge':
              return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("div", {
                className: "gratis-ai-agent-settings-section",
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("h3", {
                  className: "gratis-ai-agent-settings-section-title",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Memory', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("table", {
                  className: "form-table gratis-ai-agent-form-table",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("tbody", {
                    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Auto-Memory', 'gratis-ai-agent')
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
                          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Proactively save and recall memories', 'gratis-ai-agent'),
                          checked: local.auto_memory,
                          onChange: v => updateField('auto_memory', v),
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('When enabled, the AI can proactively save and recall memories.', 'gratis-ai-agent'),
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    })
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_components_error_boundary__WEBPACK_IMPORTED_MODULE_8__["default"], {
                  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Memory manager', 'gratis-ai-agent'),
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_memory_manager__WEBPACK_IMPORTED_MODULE_10__["default"], {})
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("h3", {
                  className: "gratis-ai-agent-settings-section-title",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Knowledge Base', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("table", {
                  className: "form-table gratis-ai-agent-form-table",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tbody", {
                    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Knowledge Base', 'gratis-ai-agent')
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
                          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Enable knowledge base search', 'gratis-ai-agent'),
                          checked: local.knowledge_enabled,
                          onChange: v => updateField('knowledge_enabled', v),
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('When enabled, the AI can search indexed documents and posts for relevant context.', 'gratis-ai-agent'),
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Auto-Index', 'gratis-ai-agent')
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
                          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Index posts on publish or update', 'gratis-ai-agent'),
                          checked: local.knowledge_auto_index,
                          onChange: v => updateField('knowledge_auto_index', v),
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Automatically index posts when they are published or updated.', 'gratis-ai-agent'),
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    })]
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_components_error_boundary__WEBPACK_IMPORTED_MODULE_8__["default"], {
                  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Knowledge manager', 'gratis-ai-agent'),
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_knowledge_manager__WEBPACK_IMPORTED_MODULE_12__["default"], {})
                })]
              });
            case 'skills':
              return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("div", {
                className: "gratis-ai-agent-settings-section",
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("h3", {
                  className: "gratis-ai-agent-settings-section-title",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Skills', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_components_error_boundary__WEBPACK_IMPORTED_MODULE_8__["default"], {
                  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Skill manager', 'gratis-ai-agent'),
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_skill_manager__WEBPACK_IMPORTED_MODULE_11__["default"], {})
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("h3", {
                  className: "gratis-ai-agent-settings-section-title",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Abilities', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("p", {
                  className: "description",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Control how each tool behaves. "Auto" runs without asking, "Confirm" pauses to ask before running, "Disabled" prevents the tool from being used.', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_abilities_manager__WEBPACK_IMPORTED_MODULE_20__["default"], {
                  abilities: abilities,
                  toolPermissions: local.tool_permissions || {},
                  onPermChange: (name, value) => {
                    const updated = {
                      ...(local.tool_permissions || {})
                    };
                    if (value === 'auto') {
                      delete updated[name];
                    } else {
                      updated[name] = value;
                    }
                    updateField('tool_permissions', updated);
                  }
                })]
              });
            case 'tools':
              return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("div", {
                className: "gratis-ai-agent-settings-section",
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("h3", {
                  className: "gratis-ai-agent-settings-section-title",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Custom Tools', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_components_error_boundary__WEBPACK_IMPORTED_MODULE_8__["default"], {
                  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Custom tools manager', 'gratis-ai-agent'),
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_custom_tools_manager__WEBPACK_IMPORTED_MODULE_14__["default"], {})
                })]
              });
            case 'automations':
              return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("div", {
                className: "gratis-ai-agent-settings-section",
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("h3", {
                  className: "gratis-ai-agent-settings-section-title",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Automations', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_components_error_boundary__WEBPACK_IMPORTED_MODULE_8__["default"], {
                  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Automations manager', 'gratis-ai-agent'),
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_automations_manager__WEBPACK_IMPORTED_MODULE_15__["default"], {})
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("h3", {
                  className: "gratis-ai-agent-settings-section-title",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Events', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_components_error_boundary__WEBPACK_IMPORTED_MODULE_8__["default"], {
                  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Events manager', 'gratis-ai-agent'),
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_events_manager__WEBPACK_IMPORTED_MODULE_16__["default"], {})
                })]
              });
            case 'agents':
              return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("div", {
                className: "gratis-ai-agent-settings-section",
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_components_error_boundary__WEBPACK_IMPORTED_MODULE_8__["default"], {
                  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Agent builder', 'gratis-ai-agent'),
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_agent_builder__WEBPACK_IMPORTED_MODULE_18__["default"], {})
                })
              });
            case 'access-branding':
              return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("div", {
                className: "gratis-ai-agent-settings-section",
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("h3", {
                  className: "gratis-ai-agent-settings-section-title",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Role Permissions', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_components_error_boundary__WEBPACK_IMPORTED_MODULE_8__["default"], {
                  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Role permissions manager', 'gratis-ai-agent'),
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_role_permissions_manager__WEBPACK_IMPORTED_MODULE_17__["default"], {})
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("h3", {
                  className: "gratis-ai-agent-settings-section-title",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Branding', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_branding_manager__WEBPACK_IMPORTED_MODULE_19__["default"], {
                  local: local,
                  updateField: updateField
                })]
              });
            case 'usage':
              return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("div", {
                className: "gratis-ai-agent-settings-section",
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_components_error_boundary__WEBPACK_IMPORTED_MODULE_8__["default"], {
                  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Usage dashboard', 'gratis-ai-agent'),
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_usage_dashboard__WEBPACK_IMPORTED_MODULE_13__["default"], {})
                })
              });
            case 'advanced':
              return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("div", {
                className: "gratis-ai-agent-settings-section",
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("h3", {
                  className: "gratis-ai-agent-settings-section-title",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Model Parameters', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("table", {
                  className: "form-table gratis-ai-agent-form-table",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tbody", {
                    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-temperature",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Temperature', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.RangeControl, {
                          id: "gratis-temperature",
                          value: local.temperature,
                          onChange: v => updateField('temperature', v),
                          min: 0,
                          max: 1,
                          step: 0.1,
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Higher = more creative, lower = more deterministic.', 'gratis-ai-agent')
                        })
                      })]
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-max-output-tokens",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Max Output Tokens', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
                          id: "gratis-max-output-tokens",
                          type: "number",
                          min: 256,
                          max: 32768,
                          value: local.max_output_tokens,
                          onChange: v => updateField('max_output_tokens', parseInt(v, 10) || 4096),
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-context-window",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Default Context Window', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
                          id: "gratis-context-window",
                          type: "number",
                          min: 4096,
                          max: 2000000,
                          value: local.context_window_default,
                          onChange: v => updateField('context_window_default', parseInt(v, 10) || 128000),
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Used as fallback when model context size is unknown.', 'gratis-ai-agent'),
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    })]
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("h3", {
                  className: "gratis-ai-agent-settings-section-title",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Integrations', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("h4", {
                  className: "gratis-ai-agent-settings-subsection-title",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Google Analytics 4', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("p", {
                  className: "description",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Connect to Google Analytics 4 to enable traffic analysis in the AI chat. You need a GA4 property ID and a Google service account JSON key with the "Viewer" role on your GA4 property.', 'gratis-ai-agent')
                }), gaStatus?.has_credentials && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
                  status: "success",
                  isDismissible: false,
                  children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Google Analytics is connected.', 'gratis-ai-agent'), ' ', gaStatus.property_id && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("strong", {
                    children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Property ID:', 'gratis-ai-agent'), ' ', gaStatus.property_id]
                  })]
                }), gaNotice && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
                  status: gaNotice.status,
                  isDismissible: true,
                  onDismiss: () => setGaNotice(null),
                  children: gaNotice.message
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("table", {
                  className: "form-table gratis-ai-agent-form-table",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tbody", {
                    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-ga-property-id",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('GA4 Property ID', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
                          id: "gratis-ga-property-id",
                          value: gaPropertyId,
                          onChange: setGaPropertyId,
                          placeholder: "123456789",
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Your numeric GA4 property ID. Found in Google Analytics > Admin > Property Settings.', 'gratis-ai-agent'),
                          __nextHasNoMarginBottom: true
                        })
                      })]
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("tr", {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("th", {
                        scope: "row",
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("label", {
                          htmlFor: "gratis-ga-service-json",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Service Account JSON Key', 'gratis-ai-agent')
                        })
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("td", {
                        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
                          id: "gratis-ga-service-json",
                          value: gaServiceJson,
                          onChange: setGaServiceJson,
                          placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Paste the contents of your service account JSON key file here.', 'gratis-ai-agent'),
                          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Download from Google Cloud Console > IAM & Admin > Service Accounts > Keys. Grant the service account "Viewer" access in GA4 Admin > Property Access Management.', 'gratis-ai-agent'),
                          rows: 6
                        })
                      })]
                    })]
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsxs)("div", {
                  className: "gratis-ai-agent-settings-row-actions",
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                    variant: "primary",
                    onClick: handleGaSave,
                    isBusy: gaSaving,
                    disabled: gaSaving || !gaPropertyId || !gaServiceJson,
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Save GA Credentials', 'gratis-ai-agent')
                  }), gaStatus?.has_credentials && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                    variant: "secondary",
                    onClick: handleGaClear,
                    isBusy: gaSaving,
                    disabled: gaSaving,
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Disconnect', 'gratis-ai-agent')
                  })]
                })]
              });
            default:
              return null;
          }
        }
      })
    }), !SELF_SAVING_TABS.includes(activeTab) && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)("div", {
      className: "gratis-ai-agent-settings-actions",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_21__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
        variant: "primary",
        onClick: handleSave,
        isBusy: saving,
        disabled: saving,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Save Settings', 'gratis-ai-agent')
      })
    })]
  });
}

/***/ },

/***/ "./src/settings-page/skill-manager.js"
/*!********************************************!*\
  !*** ./src/settings-page/skill-manager.js ***!
  \********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ SkillManager)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/backup.mjs");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/pencil.mjs");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/plus.mjs");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/trash.mjs");
/* harmony import */ var _store__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../store */ "./src/store/index.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__);
/**
 * WordPress dependencies
 */






/**
 * Internal dependencies
 */


/**
 *
 */

function SkillManager() {
  const {
    fetchSkills,
    createSkill,
    updateSkill,
    deleteSkill,
    resetSkill
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useDispatch)(_store__WEBPACK_IMPORTED_MODULE_8__["default"]);
  const {
    skills,
    skillsLoaded
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => ({
    skills: select(_store__WEBPACK_IMPORTED_MODULE_8__["default"]).getSkills(),
    skillsLoaded: select(_store__WEBPACK_IMPORTED_MODULE_8__["default"]).getSkillsLoaded()
  }), []);
  const [showForm, setShowForm] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [editId, setEditId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [formSlug, setFormSlug] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [formName, setFormName] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [formDescription, setFormDescription] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [formContent, setFormContent] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    fetchSkills();
  }, [fetchSkills]);
  const resetForm = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setShowForm(false);
    setEditId(null);
    setFormSlug('');
    setFormName('');
    setFormDescription('');
    setFormContent('');
  }, []);
  const handleSubmit = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    if (!formName.trim() || !formContent.trim()) {
      return;
    }
    if (editId) {
      await updateSkill(editId, {
        name: formName,
        description: formDescription,
        content: formContent
      });
    } else {
      if (!formSlug.trim()) {
        return;
      }
      await createSkill({
        slug: formSlug,
        name: formName,
        description: formDescription,
        content: formContent
      });
    }
    resetForm();
  }, [editId, formSlug, formName, formDescription, formContent, createSkill, updateSkill, resetForm]);
  const handleEdit = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(skill => {
    setEditId(skill.id);
    setFormSlug(skill.slug);
    setFormName(skill.name);
    setFormDescription(skill.description);
    setFormContent(skill.content);
    setShowForm(true);
  }, []);
  const handleDelete = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async id => {
    if (
    // eslint-disable-next-line no-alert
    window.confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Delete this skill?', 'gratis-ai-agent'))) {
      await deleteSkill(id);
    }
  }, [deleteSkill]);
  const handleReset = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async id => {
    if (
    // eslint-disable-next-line no-alert
    window.confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Reset this skill to its default content?', 'gratis-ai-agent'))) {
      await resetSkill(id);
    }
  }, [resetSkill]);
  const handleToggle = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async skill => {
    await updateSkill(skill.id, {
      enabled: !skill.enabled
    });
  }, [updateSkill]);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
    className: "gratis-ai-agent-skill-manager",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
      className: "gratis-ai-agent-skill-header",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("h3", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Agent Skills', 'gratis-ai-agent')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("p", {
          className: "description",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Skills are instruction guides loaded on-demand when the AI encounters a relevant task.', 'gratis-ai-agent')
        })]
      }), !showForm && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
        variant: "secondary",
        icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_6__["default"],
        onClick: () => setShowForm(true),
        size: "compact",
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Add Skill', 'gratis-ai-agent')
      })]
    }), showForm && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
      className: "gratis-ai-agent-skill-form",
      children: [!editId && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Slug', 'gratis-ai-agent'),
        value: formSlug,
        onChange: setFormSlug,
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Unique identifier (lowercase, hyphens). Cannot be changed after creation.', 'gratis-ai-agent'),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Name', 'gratis-ai-agent'),
        value: formName,
        onChange: setFormName,
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Description', 'gratis-ai-agent'),
        value: formDescription,
        onChange: setFormDescription,
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('One-line summary shown in the skill index.', 'gratis-ai-agent'),
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Instructions', 'gratis-ai-agent'),
        value: formContent,
        onChange: setFormContent,
        rows: 12,
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Full markdown instructions loaded when the AI requests this skill.', 'gratis-ai-agent')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        className: "gratis-ai-agent-skill-form-actions",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          variant: "primary",
          onClick: handleSubmit,
          disabled: !formName.trim() || !formContent.trim() || !editId && !formSlug.trim(),
          size: "compact",
          children: editId ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Update', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Create', 'gratis-ai-agent')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          variant: "tertiary",
          onClick: resetForm,
          size: "compact",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Cancel', 'gratis-ai-agent')
        })]
      })]
    }), !skillsLoaded && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("p", {
      className: "description",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Loading…', 'gratis-ai-agent')
    }), skillsLoaded && skills.length === 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("p", {
      className: "description",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('No skills found. Deactivate and reactivate the plugin to seed built-in skills.', 'gratis-ai-agent')
    }), skills.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
      className: "gratis-ai-agent-skill-cards",
      children: skills.map(skill => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        className: `gratis-ai-agent-skill-card ${!skill.enabled ? 'gratis-ai-agent-skill-card--disabled' : ''}`,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
          className: "gratis-ai-agent-skill-card-header",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
            checked: skill.enabled,
            onChange: () => handleToggle(skill),
            __nextHasNoMarginBottom: true
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
            className: "gratis-ai-agent-skill-card-title",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("strong", {
              children: skill.name
            }), skill.is_builtin && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
              className: "gratis-ai-agent-skill-badge",
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Built-in', 'gratis-ai-agent')
            })]
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("p", {
          className: "gratis-ai-agent-skill-card-description",
          children: skill.description
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
          className: "gratis-ai-agent-skill-card-footer",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("span", {
            className: "gratis-ai-agent-skill-word-count",
            children: [skill.word_count, ' ', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('words', 'gratis-ai-agent')]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
            className: "gratis-ai-agent-skill-card-actions",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
              icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__["default"],
              size: "small",
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Edit', 'gratis-ai-agent'),
              onClick: () => handleEdit(skill)
            }), skill.is_builtin ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
              icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
              size: "small",
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Reset to Default', 'gratis-ai-agent'),
              onClick: () => handleReset(skill.id)
            }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
              icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_7__["default"],
              size: "small",
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Delete', 'gratis-ai-agent'),
              isDestructive: true,
              onClick: () => handleDelete(skill.id)
            })]
          })]
        })]
      }, skill.id))
    })]
  });
}

/***/ },

/***/ "./src/settings-page/usage-dashboard.js"
/*!**********************************************!*\
  !*** ./src/settings-page/usage-dashboard.js ***!
  \**********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ UsageDashboard)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);
/**
 * WordPress dependencies
 */





/**
 *
 * @param {number|string} cost
 */

function formatCost(cost) {
  const num = parseFloat(cost) || 0;
  if (num < 0.01) {
    return '$' + num.toFixed(4);
  }
  return '$' + num.toFixed(2);
}

/**
 *
 * @param {number|string} tokens
 */
function formatTokens(tokens) {
  const num = parseInt(tokens, 10) || 0;
  if (num >= 1_000_000) {
    return (num / 1_000_000).toFixed(1) + 'M';
  }
  if (num >= 1_000) {
    return (num / 1_000).toFixed(1) + 'K';
  }
  return num.toString();
}

/**
 *
 */
function UsageDashboard() {
  const [period, setPeriod] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('30d');
  const [data, setData] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const fetchUsage = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    setLoading(true);
    try {
      const result = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gratis-ai-agent/v1/usage?period=${period}`
      });
      setData(result);
    } catch {
      setData(null);
    }
    setLoading(false);
  }, [period]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    fetchUsage();
  }, [fetchUsage]);
  if (loading) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      className: "gratis-ai-agent-usage-loading",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Spinner, {})
    });
  }
  if (!data) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to load usage data.', 'gratis-ai-agent')
    });
  }
  const totals = data.totals || {};
  const byModel = data.by_model || [];
  const maxCost = byModel.reduce((max, m) => Math.max(max, parseFloat(m.cost_usd) || 0), 0);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
    className: "gratis-ai-agent-usage-dashboard",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      className: "gratis-ai-agent-usage-header",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("h3", {
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Usage Summary', 'gratis-ai-agent')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
        value: period,
        options: [{
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Last 7 days', 'gratis-ai-agent'),
          value: '7d'
        }, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Last 30 days', 'gratis-ai-agent'),
          value: '30d'
        }, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Last 90 days', 'gratis-ai-agent'),
          value: '90d'
        }, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('All time', 'gratis-ai-agent'),
          value: 'all'
        }],
        onChange: setPeriod,
        __nextHasNoMarginBottom: true
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      className: "gratis-ai-agent-usage-cards",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        className: "gratis-ai-agent-usage-card",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          className: "gratis-ai-agent-usage-card-label",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Total Cost', 'gratis-ai-agent')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          className: "gratis-ai-agent-usage-card-value",
          children: formatCost(totals.cost_usd)
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        className: "gratis-ai-agent-usage-card",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          className: "gratis-ai-agent-usage-card-label",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Requests', 'gratis-ai-agent')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          className: "gratis-ai-agent-usage-card-value",
          children: totals.request_count || 0
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        className: "gratis-ai-agent-usage-card",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          className: "gratis-ai-agent-usage-card-label",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Input Tokens', 'gratis-ai-agent')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          className: "gratis-ai-agent-usage-card-value",
          children: formatTokens(totals.prompt_tokens)
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        className: "gratis-ai-agent-usage-card",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          className: "gratis-ai-agent-usage-card-label",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Output Tokens', 'gratis-ai-agent')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          className: "gratis-ai-agent-usage-card-value",
          children: formatTokens(totals.completion_tokens)
        })]
      })]
    }), byModel.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      className: "gratis-ai-agent-usage-breakdown",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("h4", {
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('By Model', 'gratis-ai-agent')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("table", {
        className: "gratis-ai-agent-usage-table",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("thead", {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("tr", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Model', 'gratis-ai-agent')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Requests', 'gratis-ai-agent')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Input Tokens', 'gratis-ai-agent')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Output Tokens', 'gratis-ai-agent')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Cost', 'gratis-ai-agent')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {})]
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("tbody", {
          children: byModel.map((m, i) => {
            const cost = parseFloat(m.cost_usd) || 0;
            const pct = maxCost > 0 ? cost / maxCost * 100 : 0;
            return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("tr", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("td", {
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {
                  children: m.model_id || '—'
                })
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("td", {
                children: m.request_count
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("td", {
                children: formatTokens(m.prompt_tokens)
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("td", {
                children: formatTokens(m.completion_tokens)
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("td", {
                children: formatCost(m.cost_usd)
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("td", {
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
                  className: "gratis-ai-agent-usage-bar",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
                    className: "gratis-ai-agent-usage-bar-fill",
                    style: {
                      width: pct + '%'
                    }
                  })
                })
              })]
            }, i);
          })
        })]
      })]
    })]
  });
}

/***/ },

/***/ "./src/store/index.js"
/*!****************************!*\
  !*** ./src/store/index.js ***!
  \****************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _slices_providersSlice__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./slices/providersSlice */ "./src/store/slices/providersSlice.js");
/* harmony import */ var _slices_sessionsSlice__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./slices/sessionsSlice */ "./src/store/slices/sessionsSlice.js");
/* harmony import */ var _slices_settingsSlice__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./slices/settingsSlice */ "./src/store/slices/settingsSlice.js");
/* harmony import */ var _slices_memorySlice__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./slices/memorySlice */ "./src/store/slices/memorySlice.js");
/* harmony import */ var _slices_skillsSlice__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./slices/skillsSlice */ "./src/store/slices/skillsSlice.js");
/* harmony import */ var _slices_agentsSlice__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./slices/agentsSlice */ "./src/store/slices/agentsSlice.js");
/* harmony import */ var _slices_conversationTemplatesSlice__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./slices/conversationTemplatesSlice */ "./src/store/slices/conversationTemplatesSlice.js");
/* harmony import */ var _slices_sessionFiltersSlice__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./slices/sessionFiltersSlice */ "./src/store/slices/sessionFiltersSlice.js");
/* harmony import */ var _slices_uiSlice__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./slices/uiSlice */ "./src/store/slices/uiSlice.js");
/**
 * WordPress dependencies
 */


/**
 * @typedef {import('../types').StoreState} StoreState
 * @typedef {import('../types').Provider} Provider
 * @typedef {import('../types').Session} Session
 * @typedef {import('../types').Message} Message
 * @typedef {import('../types').ToolCall} ToolCall
 * @typedef {import('../types').TokenUsage} TokenUsage
 * @typedef {import('../types').PendingConfirmation} PendingConfirmation
 * @typedef {import('../types').Settings} Settings
 * @typedef {import('../types').Memory} Memory
 * @typedef {import('../types').Skill} Skill
 */

/**
 * Domain slices — each slice owns its own state, actions, selectors, and reducer.
 */









const STORE_NAME = 'gratis-ai-agent';

// Migrate localStorage keys from old "aiAgent" prefix to "gratisAiAgent".
['Provider', 'Model', 'DebugMode'].forEach(key => {
  const oldKey = `aiAgent${key}`;
  const newKey = `gratisAiAgent${key}`;
  if (localStorage.getItem(oldKey) !== null && localStorage.getItem(newKey) === null) {
    localStorage.setItem(newKey, localStorage.getItem(oldKey));
    localStorage.removeItem(oldKey);
  }
});

/**
 * Known model context windows (tokens).
 */
const MODEL_CONTEXT_WINDOWS = {
  'claude-sonnet-4-20250514': 200000,
  'claude-opus-4-20250115': 200000,
  'gpt-4.1': 1000000,
  'gpt-4.1-mini': 1000000,
  'gpt-4.1-nano': 1000000,
  'gpt-4o': 128000,
  'gpt-4o-mini': 128000
};

/**
 * Combined initial state from all domain slices.
 */
const DEFAULT_STATE = {
  ..._slices_providersSlice__WEBPACK_IMPORTED_MODULE_1__.initialState,
  ..._slices_sessionsSlice__WEBPACK_IMPORTED_MODULE_2__.initialState,
  ..._slices_settingsSlice__WEBPACK_IMPORTED_MODULE_3__.initialState,
  ..._slices_memorySlice__WEBPACK_IMPORTED_MODULE_4__.initialState,
  ..._slices_skillsSlice__WEBPACK_IMPORTED_MODULE_5__.initialState,
  ..._slices_agentsSlice__WEBPACK_IMPORTED_MODULE_6__.initialState,
  ..._slices_conversationTemplatesSlice__WEBPACK_IMPORTED_MODULE_7__.initialState,
  ..._slices_sessionFiltersSlice__WEBPACK_IMPORTED_MODULE_8__.initialState,
  ..._slices_uiSlice__WEBPACK_IMPORTED_MODULE_9__.initialState
};

/**
 * Combined actions from all domain slices.
 */
const actions = {
  ..._slices_providersSlice__WEBPACK_IMPORTED_MODULE_1__.actions,
  ..._slices_sessionsSlice__WEBPACK_IMPORTED_MODULE_2__.actions,
  ..._slices_settingsSlice__WEBPACK_IMPORTED_MODULE_3__.actions,
  ..._slices_memorySlice__WEBPACK_IMPORTED_MODULE_4__.actions,
  ..._slices_skillsSlice__WEBPACK_IMPORTED_MODULE_5__.actions,
  ..._slices_agentsSlice__WEBPACK_IMPORTED_MODULE_6__.actions,
  ..._slices_conversationTemplatesSlice__WEBPACK_IMPORTED_MODULE_7__.actions,
  ..._slices_sessionFiltersSlice__WEBPACK_IMPORTED_MODULE_8__.actions,
  ..._slices_uiSlice__WEBPACK_IMPORTED_MODULE_9__.actions
};

/**
 * Combined selectors from all domain slices, plus cross-slice derived selectors.
 */
const selectors = {
  ..._slices_providersSlice__WEBPACK_IMPORTED_MODULE_1__.selectors,
  ..._slices_sessionsSlice__WEBPACK_IMPORTED_MODULE_2__.selectors,
  ..._slices_settingsSlice__WEBPACK_IMPORTED_MODULE_3__.selectors,
  ..._slices_memorySlice__WEBPACK_IMPORTED_MODULE_4__.selectors,
  ..._slices_skillsSlice__WEBPACK_IMPORTED_MODULE_5__.selectors,
  ..._slices_agentsSlice__WEBPACK_IMPORTED_MODULE_6__.selectors,
  ..._slices_conversationTemplatesSlice__WEBPACK_IMPORTED_MODULE_7__.selectors,
  ..._slices_sessionFiltersSlice__WEBPACK_IMPORTED_MODULE_8__.selectors,
  ..._slices_uiSlice__WEBPACK_IMPORTED_MODULE_9__.selectors,
  // ─── Cross-slice derived selectors ───────────────────────────

  /**
   * Calculate the context window usage as a percentage (0–100+).
   *
   * @param {StoreState} state
   * @return {number} Percentage of context window consumed by prompt tokens.
   */
  getContextPercentage(state) {
    const contextLimit = MODEL_CONTEXT_WINDOWS[state.selectedModelId] || state.settings?.context_window_default || 128000;
    return state.tokenUsage.prompt / contextLimit * 100;
  },
  /**
   * Whether the context window usage exceeds the 80% warning threshold.
   *
   * @param {StoreState} state
   * @return {boolean} True when context usage is above 80%.
   */
  isContextWarning(state) {
    const contextLimit = MODEL_CONTEXT_WINDOWS[state.selectedModelId] || state.settings?.context_window_default || 128000;
    return state.tokenUsage.prompt / contextLimit * 100 > 80;
  }
};

/**
 * Redux reducer for the Gratis AI Agent store.
 * Delegates to each domain slice reducer in turn.
 *
 * @param {StoreState} state  - Current state (defaults to DEFAULT_STATE).
 * @param {Object}     action - Dispatched action.
 * @return {StoreState} Next state.
 */
const reducer = (state = DEFAULT_STATE, action) => {
  // Each slice reducer handles its own action types and returns state unchanged
  // for actions it does not own. Applying them in sequence is equivalent to
  // combineReducers but preserves the flat state shape required by @wordpress/data.
  let next = state;
  next = (0,_slices_providersSlice__WEBPACK_IMPORTED_MODULE_1__.reducer)(next, action);
  next = (0,_slices_sessionsSlice__WEBPACK_IMPORTED_MODULE_2__.reducer)(next, action);
  next = (0,_slices_settingsSlice__WEBPACK_IMPORTED_MODULE_3__.reducer)(next, action);
  next = (0,_slices_memorySlice__WEBPACK_IMPORTED_MODULE_4__.reducer)(next, action);
  next = (0,_slices_skillsSlice__WEBPACK_IMPORTED_MODULE_5__.reducer)(next, action);
  next = (0,_slices_agentsSlice__WEBPACK_IMPORTED_MODULE_6__.reducer)(next, action);
  next = (0,_slices_conversationTemplatesSlice__WEBPACK_IMPORTED_MODULE_7__.reducer)(next, action);
  next = (0,_slices_sessionFiltersSlice__WEBPACK_IMPORTED_MODULE_8__.reducer)(next, action);
  next = (0,_slices_uiSlice__WEBPACK_IMPORTED_MODULE_9__.reducer)(next, action);
  return next;
};
const store = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.createReduxStore)(STORE_NAME, {
  reducer,
  actions,
  selectors
});

// Guard against double-registration: both floating-widget.js and
// screen-meta.js import this module. The first bundle to load registers
// the store; subsequent bundles on the same page skip registration so
// the existing store instance (and its state) is preserved.
if (!(0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.select)(STORE_NAME)) {
  (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.register)(store);
}
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (STORE_NAME);

/***/ },

/***/ "./src/store/slices/agentsSlice.js"
/*!*****************************************!*\
  !*** ./src/store/slices/agentsSlice.js ***!
  \*****************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   actions: () => (/* binding */ actions),
/* harmony export */   initialState: () => (/* binding */ initialState),
/* harmony export */   reducer: () => (/* binding */ reducer),
/* harmony export */   selectors: () => (/* binding */ selectors)
/* harmony export */ });
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__);
/**
 * Agents slice — agent list, selected agent, and agent CRUD thunks.
 */


const initialState = {
  agents: [],
  agentsLoaded: false,
  selectedAgentId: null
};
const actions = {
  setAgents(agents) {
    return {
      type: 'SET_AGENTS',
      agents
    };
  },
  setSelectedAgentId(agentId) {
    return {
      type: 'SET_SELECTED_AGENT_ID',
      agentId
    };
  },
  fetchAgents() {
    return async ({
      dispatch
    }) => {
      try {
        const agents = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path: '/gratis-ai-agent/v1/agents'
        });
        dispatch.setAgents(agents);
      } catch {
        dispatch.setAgents([]);
      }
    };
  },
  createAgent(data) {
    return async ({
      dispatch
    }) => {
      const agent = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: '/gratis-ai-agent/v1/agents',
        method: 'POST',
        data
      });
      dispatch.fetchAgents();
      return agent;
    };
  },
  updateAgent(id, data) {
    return async ({
      dispatch,
      select
    }) => {
      const updated = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: `/gratis-ai-agent/v1/agents/${id}`,
        method: 'PATCH',
        data
      });
      // Optimistically update the agent in the store so the card
      // reflects the new name immediately without waiting for a re-fetch.
      const current = select.getAgents();
      const merged = current.map(a => a.id === id ? {
        ...a,
        ...(updated || data)
      } : a);
      dispatch.setAgents(merged);
      // Re-fetch to confirm server state.
      dispatch.fetchAgents();
    };
  },
  deleteAgent(id) {
    return async ({
      dispatch,
      select
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: `/gratis-ai-agent/v1/agents/${id}`,
        method: 'DELETE'
      });
      // Clear selection if the deleted agent was selected.
      if (select.getSelectedAgentId() === id) {
        dispatch.setSelectedAgentId(null);
      }
      // Optimistically remove the agent from the store so the card
      // disappears immediately without waiting for a re-fetch.
      const current = select.getAgents();
      dispatch.setAgents(current.filter(a => a.id !== id));
      // Re-fetch to confirm server state.
      dispatch.fetchAgents();
    };
  }
};
const selectors = {
  getAgents(state) {
    return state.agents;
  },
  getAgentsLoaded(state) {
    return state.agentsLoaded;
  },
  getSelectedAgentId(state) {
    return state.selectedAgentId;
  },
  getSelectedAgent(state) {
    if (!state.selectedAgentId) {
      return null;
    }
    return state.agents.find(a => a.id === state.selectedAgentId) || null;
  }
};

/**
 * @param {import('../../types').StoreState} state  - Current state.
 * @param {Object}                           action - Dispatched action.
 * @return {import('../../types').StoreState} Next state.
 */
function reducer(state, action) {
  switch (action.type) {
    case 'SET_AGENTS':
      return {
        ...state,
        agents: action.agents,
        agentsLoaded: true
      };
    case 'SET_SELECTED_AGENT_ID':
      return {
        ...state,
        selectedAgentId: action.agentId
      };
    default:
      return state;
  }
}

/***/ },

/***/ "./src/store/slices/conversationTemplatesSlice.js"
/*!********************************************************!*\
  !*** ./src/store/slices/conversationTemplatesSlice.js ***!
  \********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   actions: () => (/* binding */ actions),
/* harmony export */   initialState: () => (/* binding */ initialState),
/* harmony export */   reducer: () => (/* binding */ reducer),
/* harmony export */   selectors: () => (/* binding */ selectors)
/* harmony export */ });
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__);
/**
 * Conversation templates slice — template list and CRUD thunks.
 */


const initialState = {
  conversationTemplates: [],
  conversationTemplatesLoaded: false
};
const actions = {
  setConversationTemplates(templates) {
    return {
      type: 'SET_CONVERSATION_TEMPLATES',
      templates
    };
  },
  fetchConversationTemplates(category = null) {
    return async ({
      dispatch
    }) => {
      try {
        const path = category ? `/gratis-ai-agent/v1/conversation-templates?category=${encodeURIComponent(category)}` : '/gratis-ai-agent/v1/conversation-templates';
        const templates = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path
        });
        dispatch.setConversationTemplates(templates);
      } catch {
        dispatch.setConversationTemplates([]);
      }
    };
  },
  createConversationTemplate(data) {
    return async ({
      dispatch
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: '/gratis-ai-agent/v1/conversation-templates',
        method: 'POST',
        data
      });
      dispatch.fetchConversationTemplates();
    };
  },
  updateConversationTemplate(id, data) {
    return async ({
      dispatch
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: `/gratis-ai-agent/v1/conversation-templates/${id}`,
        method: 'PATCH',
        data
      });
      dispatch.fetchConversationTemplates();
    };
  },
  deleteConversationTemplate(id) {
    return async ({
      dispatch
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: `/gratis-ai-agent/v1/conversation-templates/${id}`,
        method: 'DELETE'
      });
      dispatch.fetchConversationTemplates();
    };
  }
};
const selectors = {
  getConversationTemplates(state) {
    return state.conversationTemplates;
  },
  getConversationTemplatesLoaded(state) {
    return state.conversationTemplatesLoaded;
  }
};

/**
 * @param {import('../../types').StoreState} state  - Current state.
 * @param {Object}                           action - Dispatched action.
 * @return {import('../../types').StoreState} Next state.
 */
function reducer(state, action) {
  switch (action.type) {
    case 'SET_CONVERSATION_TEMPLATES':
      return {
        ...state,
        conversationTemplates: action.templates,
        conversationTemplatesLoaded: true
      };
    default:
      return state;
  }
}

/***/ },

/***/ "./src/store/slices/memorySlice.js"
/*!*****************************************!*\
  !*** ./src/store/slices/memorySlice.js ***!
  \*****************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   actions: () => (/* binding */ actions),
/* harmony export */   initialState: () => (/* binding */ initialState),
/* harmony export */   reducer: () => (/* binding */ reducer),
/* harmony export */   selectors: () => (/* binding */ selectors)
/* harmony export */ });
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__);
/**
 * Memory slice — memory entries CRUD.
 */

/**
 * @typedef {import('../../types').Memory} Memory
 */


const initialState = {
  memories: [],
  memoriesLoaded: false
};
const actions = {
  /**
   * Replace the memories list.
   *
   * @param {Memory[]} memories - Memory entries.
   * @return {Object} Redux action.
   */
  setMemories(memories) {
    return {
      type: 'SET_MEMORIES',
      memories
    };
  },
  /**
   * Fetch all memory entries from the REST API.
   *
   * @return {Function} Redux thunk.
   */
  fetchMemories() {
    return async ({
      dispatch
    }) => {
      try {
        const memories = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path: '/gratis-ai-agent/v1/memory'
        });
        dispatch.setMemories(memories);
      } catch {
        dispatch.setMemories([]);
      }
    };
  },
  /**
   * Create a new memory entry.
   *
   * @param {string} category - Memory category (e.g. 'general').
   * @param {string} content  - Memory content text.
   * @return {Function} Redux thunk.
   */
  createMemory(category, content) {
    return async ({
      dispatch
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: '/gratis-ai-agent/v1/memory',
        method: 'POST',
        data: {
          category,
          content
        }
      });
      dispatch.fetchMemories();
    };
  },
  /**
   * Update an existing memory entry.
   *
   * @param {number}          id   - Memory identifier.
   * @param {Partial<Memory>} data - Fields to update.
   * @return {Function} Redux thunk.
   */
  updateMemory(id, data) {
    return async ({
      dispatch
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: `/gratis-ai-agent/v1/memory/${id}`,
        method: 'PATCH',
        data
      });
      dispatch.fetchMemories();
    };
  },
  /**
   * Delete a memory entry.
   *
   * @param {number} id - Memory identifier.
   * @return {Function} Redux thunk.
   */
  deleteMemory(id) {
    return async ({
      dispatch
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: `/gratis-ai-agent/v1/memory/${id}`,
        method: 'DELETE'
      });
      dispatch.fetchMemories();
    };
  }
};
const selectors = {
  /**
   * @param {import('../../types').StoreState} state
   * @return {Memory[]} Memory entries.
   */
  getMemories(state) {
    return state.memories;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {boolean} Whether memories have been fetched.
   */
  getMemoriesLoaded(state) {
    return state.memoriesLoaded;
  }
};

/**
 * @param {import('../../types').StoreState} state  - Current state.
 * @param {Object}                           action - Dispatched action.
 * @return {import('../../types').StoreState} Next state.
 */
function reducer(state, action) {
  switch (action.type) {
    case 'SET_MEMORIES':
      return {
        ...state,
        memories: action.memories,
        memoriesLoaded: true
      };
    default:
      return state;
  }
}

/***/ },

/***/ "./src/store/slices/providersSlice.js"
/*!********************************************!*\
  !*** ./src/store/slices/providersSlice.js ***!
  \********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   actions: () => (/* binding */ actions),
/* harmony export */   initialState: () => (/* binding */ initialState),
/* harmony export */   reducer: () => (/* binding */ reducer),
/* harmony export */   selectors: () => (/* binding */ selectors)
/* harmony export */ });
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__);
/**
 * Providers slice — AI provider list and selected provider/model.
 */

/**
 * @typedef {import('../../types').Provider} Provider
 */


const initialState = {
  providers: [],
  providersLoaded: false,
  selectedProviderId: localStorage.getItem('gratisAiAgentProvider') || '',
  selectedModelId: localStorage.getItem('gratisAiAgentModel') || ''
};
const actions = {
  /**
   * Replace the providers list.
   *
   * @param {Provider[]} providers - Available AI providers.
   * @return {Object} Redux action.
   */
  setProviders(providers) {
    return {
      type: 'SET_PROVIDERS',
      providers
    };
  },
  /**
   * Select an AI provider and persist the choice to localStorage.
   *
   * @param {string} providerId - Provider identifier.
   * @return {Object} Redux action.
   */
  setSelectedProvider(providerId) {
    localStorage.setItem('gratisAiAgentProvider', providerId);
    return {
      type: 'SET_SELECTED_PROVIDER',
      providerId
    };
  },
  /**
   * Select a model and persist the choice to localStorage.
   *
   * @param {string} modelId - Model identifier.
   * @return {Object} Redux action.
   */
  setSelectedModel(modelId) {
    localStorage.setItem('gratisAiAgentModel', modelId);
    return {
      type: 'SET_SELECTED_MODEL',
      modelId
    };
  },
  /**
   * Fetch available AI providers from the REST API and populate the store.
   * Auto-selects the first provider/model when none is saved or the saved
   * provider is no longer available.
   *
   * @return {Function} Redux thunk.
   */
  fetchProviders() {
    return async ({
      dispatch
    }) => {
      try {
        const providers = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path: '/gratis-ai-agent/v1/providers'
        });
        dispatch.setProviders(providers);

        // Auto-select first provider if none saved or saved one is unavailable.
        const saved = localStorage.getItem('gratisAiAgentProvider');
        if ((!saved || !providers.find(p => p.id === saved)) && providers.length) {
          dispatch.setSelectedProvider(providers[0].id);
          if (providers[0].models?.length) {
            dispatch.setSelectedModel(providers[0].models[0].id);
          } else {
            dispatch.setSelectedModel('');
          }
        }
      } catch {
        dispatch.setProviders([]);
      }
    };
  }
};
const selectors = {
  /**
   * @param {import('../../types').StoreState} state
   * @return {Provider[]} Available AI providers.
   */
  getProviders(state) {
    return state.providers;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {boolean} Whether providers have been fetched.
   */
  getProvidersLoaded(state) {
    return state.providersLoaded;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {string} Currently selected provider ID.
   */
  getSelectedProviderId(state) {
    return state.selectedProviderId;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {string} Currently selected model ID.
   */
  getSelectedModelId(state) {
    return state.selectedModelId;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {import('../../types').ProviderModel[]} Models for the selected provider.
   */
  getSelectedProviderModels(state) {
    const provider = state.providers.find(p => p.id === state.selectedProviderId);
    return provider?.models || [];
  }
};

/**
 * @param {import('../../types').StoreState} state  - Current state.
 * @param {Object}                           action - Dispatched action.
 * @return {import('../../types').StoreState} Next state.
 */
function reducer(state, action) {
  switch (action.type) {
    case 'SET_PROVIDERS':
      return {
        ...state,
        providers: action.providers,
        providersLoaded: true
      };
    case 'SET_SELECTED_PROVIDER':
      return {
        ...state,
        selectedProviderId: action.providerId
      };
    case 'SET_SELECTED_MODEL':
      return {
        ...state,
        selectedModelId: action.modelId
      };
    default:
      return state;
  }
}

/***/ },

/***/ "./src/store/slices/sessionFiltersSlice.js"
/*!*************************************************!*\
  !*** ./src/store/slices/sessionFiltersSlice.js ***!
  \*************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   actions: () => (/* binding */ actions),
/* harmony export */   initialState: () => (/* binding */ initialState),
/* harmony export */   reducer: () => (/* binding */ reducer),
/* harmony export */   selectors: () => (/* binding */ selectors)
/* harmony export */ });
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__);
/**
 * Session filters slice — filter tab, folder, search query, and folder list.
 */


const initialState = {
  sessionFilter: 'active',
  sessionFolder: '',
  sessionSearch: '',
  folders: [],
  foldersLoaded: false
};
const actions = {
  /**
   * Set the active session filter tab.
   *
   * @param {string} filter - Filter key: 'active', 'archived', or 'trash'.
   * @return {Object} Redux action.
   */
  setSessionFilter(filter) {
    return {
      type: 'SET_SESSION_FILTER',
      filter
    };
  },
  /**
   * Set the active folder filter.
   *
   * @param {string} folder - Folder name, or empty string for all.
   * @return {Object} Redux action.
   */
  setSessionFolder(folder) {
    return {
      type: 'SET_SESSION_FOLDER',
      folder
    };
  },
  /**
   * Set the session search query.
   *
   * @param {string} search - Search string.
   * @return {Object} Redux action.
   */
  setSessionSearch(search) {
    return {
      type: 'SET_SESSION_SEARCH',
      search
    };
  },
  /**
   * Replace the folders list.
   *
   * @param {string[]} folders - Available folder names.
   * @return {Object} Redux action.
   */
  setFolders(folders) {
    return {
      type: 'SET_FOLDERS',
      folders
    };
  },
  /**
   * Fetch the list of folder names from the REST API.
   *
   * @return {Function} Redux thunk.
   */
  fetchFolders() {
    return async ({
      dispatch
    }) => {
      try {
        const folders = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path: '/gratis-ai-agent/v1/sessions/folders'
        });
        dispatch.setFolders(folders);
      } catch {
        dispatch.setFolders([]);
      }
    };
  }
};
const selectors = {
  /**
   * @param {import('../../types').StoreState} state
   * @return {string} Active session filter tab ('active', 'archived', 'trash').
   */
  getSessionFilter(state) {
    return state.sessionFilter;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {string} Active folder filter, or '' for all.
   */
  getSessionFolder(state) {
    return state.sessionFolder;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {string} Active search query.
   */
  getSessionSearch(state) {
    return state.sessionSearch;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {string[]} Available folder names.
   */
  getFolders(state) {
    return state.folders;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {boolean} Whether folders have been fetched.
   */
  getFoldersLoaded(state) {
    return state.foldersLoaded;
  }
};

/**
 * @param {import('../../types').StoreState} state  - Current state.
 * @param {Object}                           action - Dispatched action.
 * @return {import('../../types').StoreState} Next state.
 */
function reducer(state, action) {
  switch (action.type) {
    case 'SET_SESSION_FILTER':
      return {
        ...state,
        sessionFilter: action.filter
      };
    case 'SET_SESSION_FOLDER':
      return {
        ...state,
        sessionFolder: action.folder
      };
    case 'SET_SESSION_SEARCH':
      return {
        ...state,
        sessionSearch: action.search
      };
    case 'SET_FOLDERS':
      return {
        ...state,
        folders: action.folders,
        foldersLoaded: true
      };
    default:
      return state;
  }
}

/***/ },

/***/ "./src/store/slices/sessionsSlice.js"
/*!*******************************************!*\
  !*** ./src/store/slices/sessionsSlice.js ***!
  \*******************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   actions: () => (/* binding */ actions),
/* harmony export */   initialState: () => (/* binding */ initialState),
/* harmony export */   reducer: () => (/* binding */ reducer),
/* harmony export */   selectors: () => (/* binding */ selectors)
/* harmony export */ });
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/**
 * Sessions slice — session list, current session, messages, tool calls,
 * sending state, job polling, streaming, and session management thunks.
 */

/**
 * @typedef {import('../../types').Session}  Session
 * @typedef {import('../../types').Message}  Message
 * @typedef {import('../../types').ToolCall} ToolCall
 * @typedef {import('../../types').TokenUsage} TokenUsage
 * @typedef {import('../../types').PendingConfirmation} PendingConfirmation
 */



const initialState = {
  sessions: [],
  sessionsLoaded: false,
  currentSessionId: null,
  currentSessionMessages: [],
  currentSessionToolCalls: [],
  sending: false,
  currentJobId: null,
  // Token usage (current session)
  tokenUsage: {
    prompt: 0,
    completion: 0
  },
  // Live token counter (t111) — accumulated from done events.
  sessionTokens: 0,
  sessionCost: 0,
  // Per-message token data: array of { prompt, completion, cost } indexed by
  // message position. Populated when a done event arrives.
  messageTokens: [],
  // Pending confirmation (Batch 8)
  pendingConfirmation: null,
  // Action card — inline confirmation rendered in the message list (t074).
  pendingActionCard: null,
  // Streaming state — token buffer for the in-progress assistant message.
  streamingText: '',
  isStreaming: false,
  // Stream error state — true when the last stream attempt failed.
  // Used to show a "Try again" button in the message list.
  streamError: false,
  // Last user message text — stored so retryLastMessage can resend it.
  lastUserMessage: '',
  // Shared sessions — sessions shared with all admins (t077).
  sharedSessions: [],
  sharedSessionsLoaded: false,
  // Pending optimistic titles — { [sessionId]: title } set by updateSessionTitle()
  // and merged into state.sessions by SET_SESSIONS so that a fetchSessions()
  // round-trip returning "Untitled" from the server does not overwrite a title
  // that was already delivered via the SSE done event.
  pendingTitles: {}
};
const actions = {
  /**
   * Replace the sessions list.
   *
   * @param {Session[]} sessions - Session summaries.
   * @return {Object} Redux action.
   */
  setSessions(sessions) {
    return {
      type: 'SET_SESSIONS',
      sessions
    };
  },
  /**
   * Set the active session and its messages/tool-calls.
   *
   * @param {number}     sessionId - Session identifier.
   * @param {Message[]}  messages  - Messages for the session.
   * @param {ToolCall[]} toolCalls - Tool calls for the session.
   * @return {Object} Redux action.
   */
  setCurrentSession(sessionId, messages, toolCalls) {
    return {
      type: 'SET_CURRENT_SESSION',
      sessionId,
      messages,
      toolCalls
    };
  },
  /**
   * Clear the active session (start a new chat).
   *
   * Also cancels any in-flight request so the UI returns to idle state
   * immediately, allowing the empty state to render without waiting for
   * the current job to complete or error.
   *
   * @return {Function} Redux thunk.
   */
  clearCurrentSession() {
    return async ({
      dispatch,
      select
    }) => {
      // Cancel any active SSE stream.
      const controller = select.getStreamAbortController();
      if (controller) {
        controller.abort();
        dispatch.setStreamAbortController(null);
      }
      // Stop polling / sending state so the empty state renders immediately.
      dispatch.setCurrentJobId(null);
      dispatch.setSending(false);
      dispatch.setIsStreaming(false);
      dispatch.setStreamingText('');
      // Clear the session.
      dispatch({
        type: 'CLEAR_CURRENT_SESSION'
      });
    };
  },
  /**
   * Set the sending/loading state.
   *
   * @param {boolean} sending - Whether a message is in-flight.
   * @return {Object} Redux action.
   */
  setSending(sending) {
    return {
      type: 'SET_SENDING',
      sending
    };
  },
  /**
   * Set the active polling job ID.
   *
   * @param {string|null} jobId - Job identifier, or null to clear.
   * @return {Object} Redux action.
   */
  setCurrentJobId(jobId) {
    return {
      type: 'SET_CURRENT_JOB_ID',
      jobId
    };
  },
  /**
   * Append a message to the current session.
   *
   * @param {Message} message - Message to append.
   * @return {Object} Redux action.
   */
  appendMessage(message) {
    return {
      type: 'APPEND_MESSAGE',
      message
    };
  },
  /**
   * Remove the last message from the current session.
   *
   * @return {Object} Redux action.
   */
  removeLastMessage() {
    return {
      type: 'REMOVE_LAST_MESSAGE'
    };
  },
  /**
   * Update cumulative token usage for the current session.
   *
   * @param {TokenUsage} tokenUsage - Token usage counters.
   * @return {Object} Redux action.
   */
  setTokenUsage(tokenUsage) {
    return {
      type: 'SET_TOKEN_USAGE',
      tokenUsage
    };
  },
  // ─── Live token counter (t111) ───────────────────────────────

  /**
   * Accumulate session-level token counts and cost from a done event.
   *
   * @param {number} tokens - Total tokens for this exchange (prompt + completion).
   * @param {number} cost   - Estimated cost in USD for this exchange.
   * @return {Object} Redux action.
   */
  accumulateSessionTokens(tokens, cost) {
    return {
      type: 'ACCUMULATE_SESSION_TOKENS',
      tokens,
      cost
    };
  },
  /**
   * Record per-message token data at the given message index.
   *
   * @param {number} index     - Message index in currentSessionMessages.
   * @param {Object} tokenData - { prompt, completion, cost } for this message.
   * @return {Object} Redux action.
   */
  setMessageTokens(index, tokenData) {
    return {
      type: 'SET_MESSAGE_TOKENS',
      index,
      tokenData
    };
  },
  /**
   * Reset session token counters (called when a new session starts).
   *
   * @return {Object} Redux action.
   */
  resetSessionTokens() {
    return {
      type: 'RESET_SESSION_TOKENS'
    };
  },
  /**
   * Set or clear the pending tool confirmation.
   *
   * @param {PendingConfirmation|null} confirmation - Confirmation payload, or null to clear.
   * @return {Object} Redux action.
   */
  setPendingConfirmation(confirmation) {
    return {
      type: 'SET_PENDING_CONFIRMATION',
      confirmation
    };
  },
  setPendingActionCard(card) {
    return {
      type: 'SET_PENDING_ACTION_CARD',
      card
    };
  },
  /**
   * Truncate the message list to the given index (exclusive).
   *
   * @param {number} index - Keep messages[0..index-1]; discard the rest.
   * @return {Object} Redux action.
   */
  truncateMessagesTo(index) {
    return {
      type: 'TRUNCATE_MESSAGES_TO',
      index
    };
  },
  /**
   * Record the timestamp of the most recent send (for latency calculation).
   *
   * @param {number} ts - Timestamp in milliseconds since epoch.
   * @return {Object} Redux action.
   */
  setSendTimestamp(ts) {
    return {
      type: 'SET_SEND_TIMESTAMP',
      ts
    };
  },
  /**
   * Replace the streaming text buffer.
   *
   * @param {string} text - Full accumulated streaming text.
   * @return {Object} Redux action.
   */
  setStreamingText(text) {
    return {
      type: 'SET_STREAMING_TEXT',
      text
    };
  },
  /**
   * Append a token to the streaming text buffer.
   *
   * @param {string} token - Token string to append.
   * @return {Object} Redux action.
   */
  appendStreamingText(token) {
    return {
      type: 'APPEND_STREAMING_TEXT',
      token
    };
  },
  /**
   * Set whether an SSE stream is currently active.
   *
   * @param {boolean} streaming - Whether streaming is in progress.
   * @return {Object} Redux action.
   */
  setIsStreaming(streaming) {
    return {
      type: 'SET_IS_STREAMING',
      streaming
    };
  },
  /**
   * Store the AbortController for the active SSE stream.
   *
   * @param {AbortController|null} controller - Controller, or null to clear.
   * @return {Object} Redux action.
   */
  setStreamAbortController(controller) {
    return {
      type: 'SET_STREAM_ABORT_CONTROLLER',
      controller
    };
  },
  /**
   * Set or clear the stream error flag.
   *
   * @param {boolean} error - Whether the last stream attempt failed.
   * @return {Object} Redux action.
   */
  setStreamError(error) {
    return {
      type: 'SET_STREAM_ERROR',
      error
    };
  },
  /**
   * Store the last user message text for retry purposes.
   *
   * @param {string} message - The user message text.
   * @return {Object} Redux action.
   */
  setLastUserMessage(message) {
    return {
      type: 'SET_LAST_USER_MESSAGE',
      message
    };
  },
  /**
   * Replace the shared sessions list.
   *
   * @param {Session[]} sessions - Shared session summaries.
   * @return {Object} Redux action.
   */
  setSharedSessions(sessions) {
    return {
      type: 'SET_SHARED_SESSIONS',
      sessions
    };
  },
  /**
   * Optimistically update the title of a session in the sessions list.
   *
   * Called immediately after the AI generates a title so the sidebar
   * reflects the new title without waiting for a full fetchSessions round-trip.
   *
   * @param {number} sessionId - Session identifier.
   * @param {string} title     - New session title.
   * @return {Object} Redux action.
   */
  updateSessionTitle(sessionId, title) {
    return {
      type: 'UPDATE_SESSION_TITLE',
      sessionId,
      title
    };
  },
  // ─── Thunks ──────────────────────────────────────────────────

  /**
   * Fetch sessions from the REST API, applying the current filter/folder/search.
   *
   * @return {Function} Redux thunk.
   */
  fetchSessions() {
    return async ({
      dispatch,
      select
    }) => {
      try {
        const params = new URLSearchParams();
        const filter = select.getSessionFilter();
        const folder = select.getSessionFolder();
        const search = select.getSessionSearch();
        if (filter) {
          params.set('status', filter);
        }
        if (folder) {
          params.set('folder', folder);
        }
        if (search) {
          params.set('search', search);
        }
        const qs = params.toString();
        const path = '/gratis-ai-agent/v1/sessions' + (qs ? '?' + qs : '');
        const sessions = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path
        });
        dispatch.setSessions(sessions);
      } catch {
        dispatch.setSessions([]);
      }
    };
  },
  /**
   * Load a session by ID and make it the active session.
   * Restores the provider/model selection if the provider is still available.
   *
   * @param {number} sessionId - Session identifier.
   * @return {Function} Redux thunk.
   */
  openSession(sessionId) {
    return async ({
      dispatch,
      select
    }) => {
      try {
        const session = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path: `/gratis-ai-agent/v1/sessions/${sessionId}`
        });
        dispatch.setCurrentSession(session.id, session.messages || [], session.tool_calls || []);
        // Only restore provider/model if the provider is still available.
        if (session.provider_id) {
          const providers = select.getProviders();
          const providerExists = providers.some(p => p.id === session.provider_id);
          if (providerExists) {
            dispatch.setSelectedProvider(session.provider_id);
            if (session.model_id) {
              dispatch.setSelectedModel(session.model_id);
            }
          }
        }
        if (session.token_usage) {
          dispatch.setTokenUsage(session.token_usage);
        }
        // Reset live counter when switching sessions.
        dispatch.resetSessionTokens();
      } catch {
        // ignore
      }
    };
  },
  /**
   * Permanently delete a session.
   *
   * @param {number} sessionId - Session identifier.
   * @return {Function} Redux thunk.
   */
  deleteSession(sessionId) {
    return async ({
      dispatch,
      select
    }) => {
      try {
        await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path: `/gratis-ai-agent/v1/sessions/${sessionId}`,
          method: 'DELETE'
        });
        if (select.getCurrentSessionId() === sessionId) {
          dispatch.clearCurrentSession();
        }
        dispatch.fetchSessions();
      } catch {
        // ignore
      }
    };
  },
  /**
   * Pin or unpin a session.
   *
   * @param {number}  sessionId - Session identifier.
   * @param {boolean} pinned    - Whether to pin (true) or unpin (false).
   * @return {Function} Redux thunk.
   */
  pinSession(sessionId, pinned) {
    return async ({
      dispatch
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: `/gratis-ai-agent/v1/sessions/${sessionId}`,
        method: 'PATCH',
        data: {
          pinned
        }
      });
      dispatch.fetchSessions();
    };
  },
  /**
   * Archive a session (move to archived status).
   *
   * @param {number} sessionId - Session identifier.
   * @return {Function} Redux thunk.
   */
  archiveSession(sessionId) {
    return async ({
      dispatch,
      select
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: `/gratis-ai-agent/v1/sessions/${sessionId}`,
        method: 'PATCH',
        data: {
          status: 'archived'
        }
      });
      if (select.getCurrentSessionId() === sessionId) {
        dispatch.clearCurrentSession();
      }
      dispatch.fetchSessions();
    };
  },
  /**
   * Move a session to trash.
   *
   * @param {number} sessionId - Session identifier.
   * @return {Function} Redux thunk.
   */
  trashSession(sessionId) {
    return async ({
      dispatch,
      select
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: `/gratis-ai-agent/v1/sessions/${sessionId}`,
        method: 'PATCH',
        data: {
          status: 'trash'
        }
      });
      if (select.getCurrentSessionId() === sessionId) {
        dispatch.clearCurrentSession();
      }
      dispatch.fetchSessions();
    };
  },
  /**
   * Restore a session from archived or trash back to active.
   *
   * @param {number} sessionId - Session identifier.
   * @return {Function} Redux thunk.
   */
  restoreSession(sessionId) {
    return async ({
      dispatch
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: `/gratis-ai-agent/v1/sessions/${sessionId}`,
        method: 'PATCH',
        data: {
          status: 'active'
        }
      });
      dispatch.fetchSessions();
    };
  },
  /**
   * Move a session to a folder (or remove from folder when folder is empty string).
   *
   * @param {number} sessionId - Session identifier.
   * @param {string} folder    - Target folder name, or '' to remove from folder.
   * @return {Function} Redux thunk.
   */
  moveSessionToFolder(sessionId, folder) {
    return async ({
      dispatch
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: `/gratis-ai-agent/v1/sessions/${sessionId}`,
        method: 'PATCH',
        data: {
          folder
        }
      });
      dispatch.fetchSessions();
      dispatch.fetchFolders();
    };
  },
  /**
   * Rename a session.
   *
   * @param {number} sessionId - Session identifier.
   * @param {string} title     - New session title.
   * @return {Function} Redux thunk.
   */
  renameSession(sessionId, title) {
    return async ({
      dispatch
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: `/gratis-ai-agent/v1/sessions/${sessionId}`,
        method: 'PATCH',
        data: {
          title
        }
      });
      dispatch.fetchSessions();
    };
  },
  /**
   * Export a session and trigger a browser download.
   *
   * @param {number}            sessionId       - Session identifier.
   * @param {'json'|'markdown'} [format='json'] - Export format.
   * @return {Function} Redux thunk.
   */
  exportSession(sessionId, format = 'json') {
    return async () => {
      const result = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: `/gratis-ai-agent/v1/sessions/${sessionId}/export?format=${format}`
      });
      const content = format === 'json' ? JSON.stringify(result.content, null, 2) : result.content;
      const blob = new Blob([content], {
        type: format === 'json' ? 'application/json' : 'text/markdown'
      });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = result.filename;
      a.click();
      URL.revokeObjectURL(url);
    };
  },
  /**
   * Import a session from exported JSON data.
   *
   * @param {Object} data - Parsed export JSON (gratis-ai-agent-v1 format).
   * @return {Function} Redux thunk.
   */
  importSession(data) {
    return async ({
      dispatch
    }) => {
      const session = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: '/gratis-ai-agent/v1/sessions/import',
        method: 'POST',
        data
      });
      dispatch.fetchSessions();
      dispatch.openSession(session.id);
    };
  },
  /**
   * Regenerate the model response for the message at the given index.
   * Finds the preceding user message, truncates to that point, and resends.
   *
   * @param {number} index - Index of the message to regenerate from.
   * @return {Function} Redux thunk.
   */
  regenerateMessage(index) {
    return async ({
      dispatch,
      select
    }) => {
      const messages = select.getCurrentSessionMessages();
      // Find the user message at or before this index.
      let userIdx = index;
      while (userIdx >= 0 && messages[userIdx]?.role !== 'user') {
        userIdx--;
      }
      if (userIdx < 0) {
        return;
      }
      const userText = messages[userIdx]?.parts?.filter(p => p.text).map(p => p.text).join('');
      if (!userText) {
        return;
      }
      // Truncate to just before this user message.
      dispatch.truncateMessagesTo(userIdx);
      dispatch.sendMessage(userText);
    };
  },
  /**
   * Edit a user message and resend from that point.
   *
   * @param {number} index   - Index of the message to replace.
   * @param {string} newText - Replacement message text.
   * @return {Function} Redux thunk.
   */
  editAndResend(index, newText) {
    return async ({
      dispatch
    }) => {
      dispatch.truncateMessagesTo(index);
      dispatch.sendMessage(newText);
    };
  },
  /**
   * Abort any active SSE stream or polling job and reset sending state.
   *
   * @return {Function} Redux thunk.
   */
  stopGeneration() {
    return async ({
      dispatch,
      select
    }) => {
      // Abort any active SSE stream.
      const controller = select.getStreamAbortController();
      if (controller) {
        controller.abort();
        dispatch.setStreamAbortController(null);
      }
      dispatch.setCurrentJobId(null);
      dispatch.setSending(false);
      dispatch.setIsStreaming(false);
      dispatch.setStreamingText('');
    };
  },
  /**
   * Retry the last failed stream by removing the error message and
   * resending the last user message via streamMessage.
   *
   * @return {Function} Redux thunk.
   */
  retryLastMessage() {
    return async ({
      dispatch,
      select
    }) => {
      const lastMessage = select.getLastUserMessage();
      if (!lastMessage) {
        return;
      }
      // Remove the error system message appended on failure.
      dispatch.removeLastMessage();
      // Remove the user message that was appended before the failure.
      dispatch.removeLastMessage();
      // Clear the error flag.
      dispatch.setStreamError(false);
      // Resend.
      dispatch.streamMessage(lastMessage);
    };
  },
  /**
   * Send a message and stream the response token-by-token via SSE.
   *
   * Uses the Fetch API with a ReadableStream reader to consume the
   * text/event-stream response from POST /gratis-ai-agent/v1/stream.
   *
   * @param {string} message     The user message to send.
   * @param {Array}  attachments Optional array of attachment objects with
   *                             { name, type, dataUrl, isImage } shape.
   */
  streamMessage(message, attachments = []) {
    return async ({
      dispatch,
      select
    }) => {
      dispatch.setSending(true);
      dispatch.setIsStreaming(false);
      dispatch.setStreamingText('');
      dispatch.setStreamError(false);
      dispatch.setLastUserMessage(message);

      // Build message parts — text first, then image attachments.
      const parts = [];
      if (message) {
        parts.push({
          text: message
        });
      }
      const imageAttachments = attachments.filter(a => a.isImage);
      imageAttachments.forEach(att => {
        parts.push({
          image_url: att.dataUrl,
          image_name: att.name
        });
      });

      // Append user message immediately (with attachment previews).
      dispatch.appendMessage({
        role: 'user',
        parts: parts.length ? parts : [{
          text: ''
        }],
        attachments: imageAttachments
      });
      let sessionId = select.getCurrentSessionId();

      // Lazy-create session on first message.
      if (!sessionId) {
        try {
          const sessionData = {
            provider_id: select.getSelectedProviderId(),
            model_id: select.getSelectedModelId()
          };
          const agentIdForSession = select.getSelectedAgentId();
          if (agentIdForSession) {
            sessionData.agent_id = agentIdForSession;
          }
          const session = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
            path: '/gratis-ai-agent/v1/sessions',
            method: 'POST',
            data: sessionData
          });
          sessionId = session.id;
          dispatch.setCurrentSession(session.id, select.getCurrentSessionMessages(), []);
        } catch {
          dispatch.appendMessage({
            role: 'system',
            parts: [{
              text: 'Error: Failed to create session.'
            }]
          });
          dispatch.setSending(false);
          return;
        }
      }
      const body = {
        message,
        session_id: sessionId,
        provider_id: select.getSelectedProviderId(),
        model_id: select.getSelectedModelId()
      };

      // Include image attachments as base64 data URLs for vision models.
      if (attachments?.length) {
        body.attachments = attachments.map(att => ({
          name: att.name,
          type: att.type,
          data_url: att.dataUrl,
          is_image: att.isImage
        }));
      }
      const pageContext = select.getPageContext();
      if (pageContext) {
        // Normalise to object — screen-meta may set a string.
        body.page_context = typeof pageContext === 'string' ? {
          summary: pageContext
        } : pageContext;
      }
      const selectedAgentId = select.getSelectedAgentId();
      if (selectedAgentId) {
        body.agent_id = selectedAgentId;
      }
      dispatch.setSendTimestamp(Date.now());

      // Streaming was removed when all chat routing was delegated to the
      // WP AI Client SDK, which does not expose a streaming interface.
      // Fire a single synchronous POST to /chat and append the full reply
      // once the agent loop completes.
      let result;
      try {
        result = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path: '/gratis-ai-agent/v1/chat',
          method: 'POST',
          data: body
        });
      } catch (err) {
        dispatch.appendMessage({
          role: 'system',
          parts: [{
            text: `${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Error:', 'gratis-ai-agent')} ${err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to reach chat endpoint', 'gratis-ai-agent')}`
          }]
        });
        dispatch.setStreamError(true);
        dispatch.setSending(false);
        return;
      }

      // Handle tool confirmation pause.
      if (result?.awaiting_confirmation) {
        dispatch.setCurrentJobId(result.job_id);
        dispatch.setPendingConfirmation({
          jobId: result.job_id,
          tools: result.pending_tools || []
        });
        // Keep sending=true — we're still waiting for user input.
        return;
      }

      // Append the assistant reply.
      if (result?.reply) {
        const msg = {
          role: 'model',
          parts: [{
            text: result.reply
          }],
          toolCalls: result.tool_calls || []
        };
        if (select.isDebugMode()) {
          const sendTs = select.getSendTimestamp();
          const elapsed = sendTs ? Date.now() - sendTs : 0;
          const tu = result.token_usage || {};
          const completionTokens = tu.completion || 0;
          const promptTokens = tu.prompt || 0;
          const tokPerSec = elapsed > 0 ? completionTokens / (elapsed / 1000) : 0;
          const tc = result.tool_calls || [];
          const toolCalls = tc.filter(t => t.type === 'call');
          const toolNames = [...new Set(toolCalls.map(t => t.name))];
          msg.debug = {
            responseTimeMs: elapsed,
            tokenUsage: {
              prompt: promptTokens,
              completion: completionTokens
            },
            tokensPerSecond: Math.round(tokPerSec * 10) / 10,
            modelId: result.model_id || '',
            costEstimate: result.cost_estimate || 0,
            iterationsUsed: result.iterations_used || 0,
            toolCallCount: toolCalls.length,
            toolNames
          };
        }
        dispatch.appendMessage(msg);
      }
      if (result?.session_id) {
        dispatch.setCurrentSession(result.session_id, select.getCurrentSessionMessages(), select.getCurrentSessionToolCalls());
      }
      if (result?.token_usage) {
        const current = select.getTokenUsage();
        dispatch.setTokenUsage({
          prompt: current.prompt + (result.token_usage.prompt || 0),
          completion: current.completion + (result.token_usage.completion || 0)
        });
        const tu = result.token_usage;
        const totalTokens = (tu.prompt || 0) + (tu.completion || 0);
        const cost = result.cost_estimate || 0;
        dispatch.accumulateSessionTokens(totalTokens, cost);
        const msgs = select.getCurrentSessionMessages();
        const msgIndex = msgs.length - 1;
        if (msgIndex >= 0) {
          dispatch.setMessageTokens(msgIndex, {
            prompt: tu.prompt || 0,
            completion: tu.completion || 0,
            cost
          });
        }
      }
      if (result?.generated_title && result?.session_id) {
        dispatch.updateSessionTitle(result.session_id, result.generated_title);
      }
      dispatch.fetchSessions();
      dispatch.setSending(false);
    };
  },
  /**
   * Confirm a pending tool call and resume the job.
   *
   * @param {string}  jobId               - Job identifier awaiting confirmation.
   * @param {boolean} [alwaysAllow=false] - Whether to grant permanent auto-allow.
   * @return {Function} Redux thunk.
   */
  confirmToolCall(jobId, alwaysAllow = false) {
    return async ({
      dispatch
    }) => {
      dispatch.setPendingConfirmation(null);
      dispatch.setPendingActionCard(null);
      try {
        await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path: `/gratis-ai-agent/v1/job/${jobId}/confirm`,
          method: 'POST',
          data: {
            always_allow: alwaysAllow
          }
        });
        dispatch.pollJob(jobId);
      } catch (err) {
        dispatch.appendMessage({
          role: 'system',
          parts: [{
            text: `Error: ${err.message || 'Failed to confirm tool call'}`
          }]
        });
        dispatch.setSending(false);
        dispatch.setCurrentJobId(null);
      }
    };
  },
  /**
   * Reject a pending tool call and resume the job without executing the tool.
   *
   * @param {string} jobId - Job identifier awaiting confirmation.
   * @return {Function} Redux thunk.
   */
  rejectToolCall(jobId) {
    return async ({
      dispatch
    }) => {
      dispatch.setPendingConfirmation(null);
      dispatch.setPendingActionCard(null);
      try {
        await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path: `/gratis-ai-agent/v1/job/${jobId}/reject`,
          method: 'POST'
        });
        dispatch.pollJob(jobId);
      } catch (err) {
        dispatch.appendMessage({
          role: 'system',
          parts: [{
            text: `Error: ${err.message || 'Failed to reject tool call'}`
          }]
        });
        dispatch.setSending(false);
        dispatch.setCurrentJobId(null);
      }
    };
  },
  /**
   * Send a message to the synchronous /chat endpoint.
   * Delegates to streamMessage, which now performs a single POST to /chat
   * (streaming was removed when chat routing was delegated to the WP AI
   * Client SDK, which does not expose a streaming interface).
   *
   * @param {string} message     - User message text.
   * @param {Array}  attachments - Optional array of attachment objects with
   *                             { name, type, dataUrl, isImage } shape.
   * @return {Function} Redux thunk.
   */
  sendMessage(message, attachments = []) {
    return ({
      dispatch
    }) => {
      dispatch.streamMessage(message, attachments);
    };
  },
  /**
   * Poll a job until it completes, errors, or requires confirmation.
   * Retries every 3 seconds up to 200 attempts (~10 minutes).
   *
   * @param {string} jobId - Job identifier to poll.
   * @return {Function} Redux thunk.
   */
  pollJob(jobId) {
    return async ({
      dispatch,
      select
    }) => {
      let attempts = 0;
      const maxAttempts = 200;
      const poll = async () => {
        attempts++;
        if (attempts > maxAttempts) {
          dispatch.appendMessage({
            role: 'system',
            parts: [{
              text: 'Error: Request timed out.'
            }]
          });
          dispatch.setSending(false);
          dispatch.setCurrentJobId(null);
          return;
        }

        // If job was cancelled (different jobId now), stop.
        if (select.getCurrentJobId() !== jobId) {
          return;
        }
        try {
          const result = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
            path: `/gratis-ai-agent/v1/job/${jobId}`
          });
          if (result.status === 'processing') {
            setTimeout(poll, 3000);
            return;
          }
          if (result.status === 'awaiting_confirmation') {
            const cardData = {
              jobId,
              tools: result.pending_tools || []
            };
            dispatch.setPendingConfirmation(cardData);
            dispatch.setPendingActionCard(cardData);
            // Don't clear sending — we're still waiting.
            return;
          }
          if (result.status === 'error') {
            dispatch.appendMessage({
              role: 'system',
              parts: [{
                text: `Error: ${result.message || 'Unknown error'}`
              }]
            });
          }
          if (result.status === 'complete') {
            // Add assistant reply.
            if (result.reply) {
              const msg = {
                role: 'model',
                parts: [{
                  text: result.reply
                }],
                toolCalls: result.tool_calls
              };

              // Attach debug metadata when debug mode is active.
              if (select.isDebugMode()) {
                const sendTs = select.getSendTimestamp();
                const elapsed = sendTs ? Date.now() - sendTs : 0;
                const tu = result.token_usage || {};
                const completionTokens = tu.completion || 0;
                const promptTokens = tu.prompt || 0;
                const tokPerSec = elapsed > 0 ? completionTokens / (elapsed / 1000) : 0;

                // Derive tool call count and names.
                const tc = result.tool_calls || [];
                const toolCalls = tc.filter(t => t.type === 'call');
                const toolNames = [...new Set(toolCalls.map(t => t.name))];
                msg.debug = {
                  responseTimeMs: elapsed,
                  tokenUsage: {
                    prompt: promptTokens,
                    completion: completionTokens
                  },
                  tokensPerSecond: Math.round(tokPerSec * 10) / 10,
                  modelId: result.model_id || '',
                  costEstimate: result.cost_estimate || 0,
                  iterationsUsed: result.iterations_used || 0,
                  toolCallCount: toolCalls.length,
                  toolNames
                };
              }
              dispatch.appendMessage(msg);
            }
            if (result.session_id) {
              dispatch.setCurrentSession(result.session_id, select.getCurrentSessionMessages(), select.getCurrentSessionToolCalls());
            }

            // Update token usage.
            if (result.token_usage) {
              const current = select.getTokenUsage();
              dispatch.setTokenUsage({
                prompt: current.prompt + (result.token_usage.prompt || 0),
                completion: current.completion + (result.token_usage.completion || 0)
              });

              // Live token counter (t111).
              const tu = result.token_usage;
              const totalTokens = (tu.prompt || 0) + (tu.completion || 0);
              const cost = result.cost_estimate || 0;
              dispatch.accumulateSessionTokens(totalTokens, cost);
              const msgs = select.getCurrentSessionMessages();
              const msgIndex = msgs.length - 1;
              if (msgIndex >= 0) {
                dispatch.setMessageTokens(msgIndex, {
                  prompt: tu.prompt || 0,
                  completion: tu.completion || 0,
                  cost
                });
              }
            }

            // Optimistically update the session title in the sidebar
            // when the server generated one (first message only).
            if (result.generated_title && result.session_id) {
              dispatch.updateSessionTitle(result.session_id, result.generated_title);
            }
            dispatch.fetchSessions();
          }
        } catch {
          // Network blip — keep polling.
          setTimeout(poll, 3000);
          return;
        }
        dispatch.setSending(false);
        dispatch.setCurrentJobId(null);
      };
      setTimeout(poll, 2000);
    };
  },
  /**
   * Compact the current conversation into a new session with a summary.
   * Builds a text summary of all messages, creates a new session, and
   * sends the summary as the first message to preserve context.
   *
   * @return {Function} Redux thunk.
   */
  compactConversation() {
    return async ({
      dispatch,
      select
    }) => {
      const messages = select.getCurrentSessionMessages();
      if (!messages.length) {
        return;
      }

      // Build a summary request.
      const summaryText = messages.map(m => {
        const role = m.role === 'model' ? 'Assistant' : 'User';
        const text = m.parts?.filter(p => p.text).map(p => p.text).join('');
        return text ? `${role}: ${text}` : null;
      }).filter(Boolean).join('\n');

      // Create a new session.
      try {
        const session = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path: '/gratis-ai-agent/v1/sessions',
          method: 'POST',
          data: {
            title: 'Compacted conversation',
            provider_id: select.getSelectedProviderId(),
            model_id: select.getSelectedModelId()
          }
        });

        // Send the summary as the first message in the new session.
        dispatch.setCurrentSession(session.id, [], []);
        dispatch.setTokenUsage({
          prompt: 0,
          completion: 0
        });
        dispatch.resetSessionTokens();
        dispatch.sendMessage('Please provide a concise summary of this conversation so we can continue in a fresh context:\n\n' + summaryText);
        dispatch.fetchSessions();
      } catch {
        // ignore
      }
    };
  },
  /**
   * Fetch all sessions shared with admins.
   *
   * @return {Function} Redux thunk.
   */
  fetchSharedSessions() {
    return async ({
      dispatch
    }) => {
      try {
        const sessions = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path: '/gratis-ai-agent/v1/sessions/shared'
        });
        dispatch.setSharedSessions(sessions);
      } catch {
        dispatch.setSharedSessions([]);
      }
    };
  },
  /**
   * Share a session with all admins.
   *
   * @param {number} sessionId - Session identifier.
   * @return {Function} Redux thunk.
   */
  shareSession(sessionId) {
    return async ({
      dispatch
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: `/gratis-ai-agent/v1/sessions/${sessionId}/share`,
        method: 'POST'
      });
      dispatch.fetchSessions();
      dispatch.fetchSharedSessions();
    };
  },
  /**
   * Unshare a session (remove from shared sessions).
   *
   * @param {number} sessionId - Session identifier.
   * @return {Function} Redux thunk.
   */
  unshareSession(sessionId) {
    return async ({
      dispatch
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: `/gratis-ai-agent/v1/sessions/${sessionId}/share`,
        method: 'DELETE'
      });
      dispatch.fetchSessions();
      dispatch.fetchSharedSessions();
    };
  }
};
const selectors = {
  /**
   * @param {import('../../types').StoreState} state
   * @return {Session[]} Session list.
   */
  getSessions(state) {
    return state.sessions;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {boolean} Whether sessions have been fetched.
   */
  getSessionsLoaded(state) {
    return state.sessionsLoaded;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {number|null} Active session ID, or null.
   */
  getCurrentSessionId(state) {
    return state.currentSessionId;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {Message[]} Messages in the active session.
   */
  getCurrentSessionMessages(state) {
    return state.currentSessionMessages;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {ToolCall[]} Tool calls in the active session.
   */
  getCurrentSessionToolCalls(state) {
    return state.currentSessionToolCalls;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {boolean} Whether a message is in-flight.
   */
  isSending(state) {
    return state.sending;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {string|null} Active polling job ID, or null.
   */
  getCurrentJobId(state) {
    return state.currentJobId;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {import('../../types').TokenUsage} Cumulative token usage for the current session.
   */
  getTokenUsage(state) {
    return state.tokenUsage;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {number} Accumulated session token count (prompt + completion).
   */
  getSessionTokens(state) {
    return state.sessionTokens;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {number} Accumulated session cost estimate in USD.
   */
  getSessionCost(state) {
    return state.sessionCost;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {Array} Per-message token data array.
   */
  getMessageTokens(state) {
    return state.messageTokens;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {import('../../types').PendingConfirmation|null} Pending tool confirmation, or null.
   */
  getPendingConfirmation(state) {
    return state.pendingConfirmation;
  },
  // Pending action card (inline confirmation in message list, t074)
  getPendingActionCard(state) {
    return state.pendingActionCard;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {string} Accumulated streaming text buffer.
   */
  getStreamingText(state) {
    return state.streamingText;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {boolean} Whether an SSE stream is currently active.
   */
  isStreamingActive(state) {
    return state.isStreaming;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {AbortController|null} Controller for the active stream, or null.
   */
  getStreamAbortController(state) {
    return state.streamAbortController || null;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {boolean} Whether the last stream attempt failed with an error.
   */
  hasStreamError(state) {
    return state.streamError;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {string} The last user message text (for retry).
   */
  getLastUserMessage(state) {
    return state.lastUserMessage;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {Session[]} Sessions shared with all admins.
   */
  getSharedSessions(state) {
    return state.sharedSessions;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {boolean} Whether shared sessions have been fetched.
   */
  getSharedSessionsLoaded(state) {
    return state.sharedSessionsLoaded;
  }
};

/**
 * @param {import('../../types').StoreState} state  - Current state.
 * @param {Object}                           action - Dispatched action.
 * @return {import('../../types').StoreState} Next state.
 */
function reducer(state, action) {
  switch (action.type) {
    case 'SET_SESSIONS':
      {
        // Merge any pending optimistic titles into the incoming sessions list.
        // When updateSessionTitle() fires before fetchSessions() returns, the
        // server response may still carry "Untitled" (the AI title is generated
        // client-side from the SSE done event and never written back to the DB
        // in the same request). Preserving the optimistic title here ensures the
        // sidebar reflects the generated title even after the fetchSessions()
        // round-trip completes.
        const pending = state.pendingTitles || {};
        const sessions = action.sessions.map(s => {
          const optimistic = pending[s.id];
          return optimistic ? {
            ...s,
            title: optimistic
          } : s;
        });
        return {
          ...state,
          sessions,
          sessionsLoaded: true,
          pendingTitles: {}
        };
      }
    case 'SET_CURRENT_SESSION':
      return {
        ...state,
        currentSessionId: action.sessionId,
        currentSessionMessages: action.messages,
        currentSessionToolCalls: action.toolCalls
      };
    case 'CLEAR_CURRENT_SESSION':
      return {
        ...state,
        currentSessionId: null,
        currentSessionMessages: [],
        currentSessionToolCalls: [],
        tokenUsage: {
          prompt: 0,
          completion: 0
        },
        sessionTokens: 0,
        sessionCost: 0,
        messageTokens: []
      };
    case 'SET_SENDING':
      return {
        ...state,
        sending: action.sending
      };
    case 'SET_CURRENT_JOB_ID':
      return {
        ...state,
        currentJobId: action.jobId
      };
    case 'APPEND_MESSAGE':
      return {
        ...state,
        currentSessionMessages: [...state.currentSessionMessages, action.message]
      };
    case 'REMOVE_LAST_MESSAGE':
      return {
        ...state,
        currentSessionMessages: state.currentSessionMessages.slice(0, -1)
      };
    case 'SET_TOKEN_USAGE':
      return {
        ...state,
        tokenUsage: action.tokenUsage
      };
    case 'ACCUMULATE_SESSION_TOKENS':
      return {
        ...state,
        sessionTokens: state.sessionTokens + action.tokens,
        sessionCost: state.sessionCost + action.cost
      };
    case 'SET_MESSAGE_TOKENS':
      {
        const newMessageTokens = [...state.messageTokens];
        newMessageTokens[action.index] = action.tokenData;
        return {
          ...state,
          messageTokens: newMessageTokens
        };
      }
    case 'RESET_SESSION_TOKENS':
      return {
        ...state,
        sessionTokens: 0,
        sessionCost: 0,
        messageTokens: []
      };
    case 'SET_PENDING_CONFIRMATION':
      return {
        ...state,
        pendingConfirmation: action.confirmation
      };
    case 'SET_PENDING_ACTION_CARD':
      return {
        ...state,
        pendingActionCard: action.card
      };
    case 'TRUNCATE_MESSAGES_TO':
      return {
        ...state,
        currentSessionMessages: state.currentSessionMessages.slice(0, action.index)
      };
    case 'SET_SEND_TIMESTAMP':
      return {
        ...state,
        sendTimestamp: action.ts
      };
    case 'SET_STREAMING_TEXT':
      return {
        ...state,
        streamingText: action.text
      };
    case 'APPEND_STREAMING_TEXT':
      return {
        ...state,
        streamingText: state.streamingText + action.token
      };
    case 'SET_IS_STREAMING':
      return {
        ...state,
        isStreaming: action.streaming
      };
    case 'SET_STREAM_ABORT_CONTROLLER':
      return {
        ...state,
        streamAbortController: action.controller
      };
    case 'SET_STREAM_ERROR':
      return {
        ...state,
        streamError: action.error
      };
    case 'SET_LAST_USER_MESSAGE':
      return {
        ...state,
        lastUserMessage: action.message
      };
    case 'SET_SHARED_SESSIONS':
      return {
        ...state,
        sharedSessions: action.sessions,
        sharedSessionsLoaded: true
      };
    case 'UPDATE_SESSION_TITLE':
      {
        const exists = state.sessions.some(s => parseInt(s.id, 10) === action.sessionId);
        // If the session is already in the list, update its title in place.
        // If it is not yet in the list (e.g. a brand-new session whose
        // setCurrentSession ran before fetchSessions populated state.sessions),
        // prepend a minimal stub so the sidebar shows the generated title
        // immediately without waiting for the fetchSessions round-trip.
        const updatedSessions = exists ? state.sessions.map(s => parseInt(s.id, 10) === action.sessionId ? {
          ...s,
          title: action.title
        } : s) : [{
          id: action.sessionId,
          title: action.title,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
          status: 'active',
          message_count: 0
        }, ...state.sessions];
        // Record the title in pendingTitles so SET_SESSIONS can preserve it
        // when the subsequent fetchSessions() round-trip returns "Untitled"
        // from the server (the server never writes the AI-generated title back
        // to the DB in the same request cycle).
        return {
          ...state,
          sessions: updatedSessions,
          pendingTitles: {
            ...(state.pendingTitles || {}),
            [action.sessionId]: action.title
          }
        };
      }
    default:
      return state;
  }
}

/***/ },

/***/ "./src/store/slices/settingsSlice.js"
/*!*******************************************!*\
  !*** ./src/store/slices/settingsSlice.js ***!
  \*******************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   actions: () => (/* binding */ actions),
/* harmony export */   initialState: () => (/* binding */ initialState),
/* harmony export */   reducer: () => (/* binding */ reducer),
/* harmony export */   selectors: () => (/* binding */ selectors)
/* harmony export */ });
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__);
/**
 * Settings slice — plugin settings.
 */

/**
 * @typedef {import('../../types').Settings} Settings
 */


const initialState = {
  settings: null,
  settingsLoaded: false
};
const actions = {
  /**
   * Replace the plugin settings.
   *
   * @param {Settings} settings - Plugin settings object.
   * @return {Object} Redux action.
   */
  setSettings(settings) {
    return {
      type: 'SET_SETTINGS',
      settings
    };
  },
  /**
   * Fetch plugin settings from the REST API.
   *
   * @return {Function} Redux thunk.
   */
  fetchSettings() {
    return async ({
      dispatch
    }) => {
      try {
        const settings = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path: '/gratis-ai-agent/v1/settings'
        });
        dispatch.setSettings(settings);
      } catch {
        dispatch.setSettings({});
      }
    };
  },
  /**
   * Save plugin settings via the REST API.
   *
   * @param {Partial<Settings>} data - Settings fields to update.
   * @return {Function} Redux thunk that resolves with the saved settings.
   */
  saveSettings(data) {
    return async ({
      dispatch
    }) => {
      try {
        const settings = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path: '/gratis-ai-agent/v1/settings',
          method: 'POST',
          data
        });
        dispatch.setSettings(settings);
        return settings;
      } catch (err) {
        throw err;
      }
    };
  }
};
const selectors = {
  /**
   * @param {import('../../types').StoreState} state
   * @return {Settings|null} Plugin settings, or null if not yet loaded.
   */
  getSettings(state) {
    return state.settings;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {boolean} Whether settings have been fetched.
   */
  getSettingsLoaded(state) {
    return state.settingsLoaded;
  },
  // YOLO mode (skip all confirmations)
  isYoloMode(state) {
    return state.settings?.yolo_mode ?? false;
  }
};

/**
 * @param {import('../../types').StoreState} state  - Current state.
 * @param {Object}                           action - Dispatched action.
 * @return {import('../../types').StoreState} Next state.
 */
function reducer(state, action) {
  switch (action.type) {
    case 'SET_SETTINGS':
      return {
        ...state,
        settings: action.settings,
        settingsLoaded: true
      };
    default:
      return state;
  }
}

/***/ },

/***/ "./src/store/slices/skillsSlice.js"
/*!*****************************************!*\
  !*** ./src/store/slices/skillsSlice.js ***!
  \*****************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   actions: () => (/* binding */ actions),
/* harmony export */   initialState: () => (/* binding */ initialState),
/* harmony export */   reducer: () => (/* binding */ reducer),
/* harmony export */   selectors: () => (/* binding */ selectors)
/* harmony export */ });
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__);
/**
 * Skills slice — skill entries CRUD.
 */

/**
 * @typedef {import('../../types').Skill} Skill
 */


const initialState = {
  skills: [],
  skillsLoaded: false
};
const actions = {
  /**
   * Replace the skills list.
   *
   * @param {Skill[]} skills - Skill entries.
   * @return {Object} Redux action.
   */
  setSkills(skills) {
    return {
      type: 'SET_SKILLS',
      skills
    };
  },
  /**
   * Fetch all skill entries from the REST API.
   *
   * @return {Function} Redux thunk.
   */
  fetchSkills() {
    return async ({
      dispatch
    }) => {
      try {
        const skills = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path: '/gratis-ai-agent/v1/skills'
        });
        dispatch.setSkills(skills);
      } catch {
        dispatch.setSkills([]);
      }
    };
  },
  /**
   * Create a new skill.
   *
   * @param {Partial<Skill>} data - Skill fields.
   * @return {Function} Redux thunk.
   */
  createSkill(data) {
    return async ({
      dispatch
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: '/gratis-ai-agent/v1/skills',
        method: 'POST',
        data
      });
      dispatch.fetchSkills();
    };
  },
  /**
   * Update an existing skill.
   *
   * @param {number}         id   - Skill identifier.
   * @param {Partial<Skill>} data - Fields to update.
   * @return {Function} Redux thunk.
   */
  updateSkill(id, data) {
    return async ({
      dispatch
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: `/gratis-ai-agent/v1/skills/${id}`,
        method: 'PATCH',
        data
      });
      dispatch.fetchSkills();
    };
  },
  /**
   * Delete a skill.
   *
   * @param {number} id - Skill identifier.
   * @return {Function} Redux thunk.
   */
  deleteSkill(id) {
    return async ({
      dispatch
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: `/gratis-ai-agent/v1/skills/${id}`,
        method: 'DELETE'
      });
      dispatch.fetchSkills();
    };
  },
  /**
   * Reset a skill to its built-in defaults.
   *
   * @param {number} id - Skill identifier.
   * @return {Function} Redux thunk.
   */
  resetSkill(id) {
    return async ({
      dispatch
    }) => {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
        path: `/gratis-ai-agent/v1/skills/${id}/reset`,
        method: 'POST'
      });
      dispatch.fetchSkills();
    };
  }
};
const selectors = {
  /**
   * @param {import('../../types').StoreState} state
   * @return {Skill[]} Skill entries.
   */
  getSkills(state) {
    return state.skills;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {boolean} Whether skills have been fetched.
   */
  getSkillsLoaded(state) {
    return state.skillsLoaded;
  }
};

/**
 * @param {import('../../types').StoreState} state  - Current state.
 * @param {Object}                           action - Dispatched action.
 * @return {import('../../types').StoreState} Next state.
 */
function reducer(state, action) {
  switch (action.type) {
    case 'SET_SKILLS':
      return {
        ...state,
        skills: action.skills,
        skillsLoaded: true
      };
    default:
      return state;
  }
}

/***/ },

/***/ "./src/store/slices/uiSlice.js"
/*!*************************************!*\
  !*** ./src/store/slices/uiSlice.js ***!
  \*************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   actions: () => (/* binding */ actions),
/* harmony export */   initialState: () => (/* binding */ initialState),
/* harmony export */   reducer: () => (/* binding */ reducer),
/* harmony export */   selectors: () => (/* binding */ selectors)
/* harmony export */ });
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__);
/**
 * UI slice — floating panel, debug mode, alerts, page context,
 * site builder mode, text-to-speech, and send timestamp.
 */


const initialState = {
  floatingOpen: false,
  floatingMinimized: false,
  pageContext: '',
  // Debug mode
  debugMode: localStorage.getItem('gratisAiAgentDebugMode') === 'true',
  sendTimestamp: 0,
  // Proactive alerts — count of issues surfaced as a badge on the FAB.
  alertCount: 0,
  // Site builder mode — true when a fresh WordPress install is detected.
  // Seeded from the PHP-injected global so the widget can open immediately
  // without waiting for a REST round-trip.
  siteBuilderMode: window.gratisAiAgentSiteBuilder?.siteBuilderMode ?? false,
  isFreshInstall: window.gratisAiAgentSiteBuilder?.isFreshInstall ?? false,
  siteBuilderStep: 0,
  siteBuilderTotalSteps: 0,
  // Text-to-speech (t084) — persisted to localStorage.
  ttsEnabled: localStorage.getItem('gratisAiAgentTtsEnabled') === 'true',
  ttsVoiceURI: localStorage.getItem('gratisAiAgentTtsVoiceURI') || '',
  ttsRate: parseFloat(localStorage.getItem('gratisAiAgentTtsRate') || '1'),
  ttsPitch: parseFloat(localStorage.getItem('gratisAiAgentTtsPitch') || '1')
};
const actions = {
  /**
   * Open or close the floating panel.
   *
   * @param {boolean} open - Whether the panel should be open.
   * @return {Object} Redux action.
   */
  setFloatingOpen(open) {
    return {
      type: 'SET_FLOATING_OPEN',
      open
    };
  },
  /**
   * Minimize or expand the floating panel.
   *
   * @param {boolean} minimized - Whether the panel should be minimized.
   * @return {Object} Redux action.
   */
  setFloatingMinimized(minimized) {
    return {
      type: 'SET_FLOATING_MINIMIZED',
      minimized
    };
  },
  /**
   * Enable or disable site builder mode.
   *
   * @param {boolean} enabled - Whether site builder mode should be active.
   * @return {Object} Redux action.
   */
  setSiteBuilderMode(enabled) {
    return {
      type: 'SET_SITE_BUILDER_MODE',
      enabled
    };
  },
  /**
   * Set structured page context for the AI.
   *
   * @param {string|Object} context - Page context object or string.
   * @return {Object} Redux action.
   */
  setPageContext(context) {
    return {
      type: 'SET_PAGE_CONTEXT',
      context
    };
  },
  /**
   * Enable or disable debug mode and persist the choice to localStorage.
   *
   * @param {boolean} enabled - Whether debug mode should be active.
   * @return {Object} Redux action.
   */
  setDebugMode(enabled) {
    localStorage.setItem('gratisAiAgentDebugMode', enabled ? 'true' : 'false');
    return {
      type: 'SET_DEBUG_MODE',
      enabled
    };
  },
  setAlertCount(count) {
    return {
      type: 'SET_ALERT_COUNT',
      count
    };
  },
  /**
   * Set the current step number in the site builder progress indicator.
   *
   * @param {number} step - Current step (0-based).
   * @return {Object} Redux action.
   */
  setSiteBuilderStep(step) {
    return {
      type: 'SET_SITE_BUILDER_STEP',
      step
    };
  },
  /**
   * Set the total number of steps in the site builder progress indicator.
   *
   * @param {number} total - Total step count.
   * @return {Object} Redux action.
   */
  setSiteBuilderTotalSteps(total) {
    return {
      type: 'SET_SITE_BUILDER_TOTAL_STEPS',
      total
    };
  },
  // ─── Text-to-speech (t084) ───────────────────────────────────

  /**
   * Enable or disable text-to-speech and persist the choice to localStorage.
   *
   * @param {boolean} enabled - Whether TTS should be active.
   * @return {Object} Redux action.
   */
  setTtsEnabled(enabled) {
    localStorage.setItem('gratisAiAgentTtsEnabled', enabled ? 'true' : 'false');
    return {
      type: 'SET_TTS_ENABLED',
      enabled
    };
  },
  /**
   * Set the TTS voice URI and persist to localStorage.
   *
   * @param {string} voiceURI - SpeechSynthesisVoice.voiceURI value.
   * @return {Object} Redux action.
   */
  setTtsVoiceURI(voiceURI) {
    localStorage.setItem('gratisAiAgentTtsVoiceURI', voiceURI);
    return {
      type: 'SET_TTS_VOICE_URI',
      voiceURI
    };
  },
  /**
   * Set the TTS speech rate and persist to localStorage.
   *
   * @param {number} rate - Speech rate (0.1–10).
   * @return {Object} Redux action.
   */
  setTtsRate(rate) {
    localStorage.setItem('gratisAiAgentTtsRate', String(rate));
    return {
      type: 'SET_TTS_RATE',
      rate
    };
  },
  /**
   * Set the TTS speech pitch and persist to localStorage.
   *
   * @param {number} pitch - Speech pitch (0–2).
   * @return {Object} Redux action.
   */
  setTtsPitch(pitch) {
    localStorage.setItem('gratisAiAgentTtsPitch', String(pitch));
    return {
      type: 'SET_TTS_PITCH',
      pitch
    };
  },
  fetchAlerts() {
    return async ({
      dispatch
    }) => {
      try {
        const data = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path: '/gratis-ai-agent/v1/alerts'
        });
        dispatch.setAlertCount(data.count || 0);
      } catch {
        // Non-fatal — badge simply stays at 0 on error.
        dispatch.setAlertCount(0);
      }
    };
  }
};
const selectors = {
  /**
   * @param {import('../../types').StoreState} state
   * @return {boolean} Whether the floating panel is open.
   */
  isFloatingOpen(state) {
    return state.floatingOpen;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {boolean} Whether the floating panel is minimized.
   */
  isFloatingMinimized(state) {
    return state.floatingMinimized;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {boolean} Whether site builder mode is active.
   */
  isSiteBuilderMode(state) {
    return state.siteBuilderMode;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {boolean} Whether the current site is a fresh WordPress install.
   */
  isFreshInstall(state) {
    return state.isFreshInstall;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {number} Current step in the site builder progress indicator.
   */
  getSiteBuilderStep(state) {
    return state.siteBuilderStep ?? 0;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {number} Total steps in the site builder progress indicator.
   */
  getSiteBuilderTotalSteps(state) {
    return state.siteBuilderTotalSteps ?? 0;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {string|Object} Structured page context for the AI.
   */
  getPageContext(state) {
    return state.pageContext;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {boolean} Whether debug mode is active.
   */
  isDebugMode(state) {
    return state.debugMode;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {number} Timestamp of the last send in ms since epoch.
   */
  getSendTimestamp(state) {
    return state.sendTimestamp;
  },
  getAlertCount(state) {
    return state.alertCount;
  },
  // Text-to-speech (t084)

  /**
   * @param {import('../../types').StoreState} state
   * @return {boolean} Whether text-to-speech is enabled.
   */
  isTtsEnabled(state) {
    return state.ttsEnabled;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {string} Selected TTS voice URI (empty = browser default).
   */
  getTtsVoiceURI(state) {
    return state.ttsVoiceURI;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {number} TTS speech rate.
   */
  getTtsRate(state) {
    return state.ttsRate;
  },
  /**
   * @param {import('../../types').StoreState} state
   * @return {number} TTS speech pitch.
   */
  getTtsPitch(state) {
    return state.ttsPitch;
  }
};

/**
 * @param {import('../../types').StoreState} state  - Current state.
 * @param {Object}                           action - Dispatched action.
 * @return {import('../../types').StoreState} Next state.
 */
function reducer(state, action) {
  switch (action.type) {
    case 'SET_FLOATING_OPEN':
      return {
        ...state,
        floatingOpen: action.open
      };
    case 'SET_FLOATING_MINIMIZED':
      return {
        ...state,
        floatingMinimized: action.minimized
      };
    case 'SET_SITE_BUILDER_MODE':
      return {
        ...state,
        siteBuilderMode: action.enabled
      };
    case 'SET_PAGE_CONTEXT':
      return {
        ...state,
        pageContext: action.context
      };
    case 'SET_DEBUG_MODE':
      return {
        ...state,
        debugMode: action.enabled
      };
    case 'SET_ALERT_COUNT':
      return {
        ...state,
        alertCount: action.count
      };
    case 'SET_SITE_BUILDER_STEP':
      return {
        ...state,
        siteBuilderStep: action.step
      };
    case 'SET_SITE_BUILDER_TOTAL_STEPS':
      return {
        ...state,
        siteBuilderTotalSteps: action.total
      };
    case 'SET_TTS_ENABLED':
      return {
        ...state,
        ttsEnabled: action.enabled
      };
    case 'SET_TTS_VOICE_URI':
      return {
        ...state,
        ttsVoiceURI: action.voiceURI
      };
    case 'SET_TTS_RATE':
      return {
        ...state,
        ttsRate: action.rate
      };
    case 'SET_TTS_PITCH':
      return {
        ...state,
        ttsPitch: action.pitch
      };
    default:
      return state;
  }
}

/***/ },

/***/ "./src/unified-admin/context.js"
/*!**************************************!*\
  !*** ./src/unified-admin/context.js ***!
  \**************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   AppProvider: () => (/* binding */ AppProvider),
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__),
/* harmony export */   useApp: () => (/* binding */ useApp)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/**
 * WordPress dependencies
 */

const AppContext = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createContext)(null);
const AppProvider = AppContext.Provider;

/**
 * Use the app context.
 *
 * @return {Object} App context value.
 */
function useApp() {
  const context = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useContext)(AppContext);
  if (!context) {
    throw new Error('useApp must be used within an AppProvider');
  }
  return context;
}
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (AppContext);

/***/ },

/***/ "./src/unified-admin/index.js"
/*!************************************!*\
  !*** ./src/unified-admin/index.js ***!
  \************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _style_css__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./style.css */ "./src/unified-admin/style.css");
/* harmony import */ var _router__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./router */ "./src/unified-admin/router.js");
/* harmony import */ var _context__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./context */ "./src/unified-admin/context.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);
/**
 * WordPress dependencies
 */



/**
 * Internal dependencies
 */




/**
 * Derive the initial route from the URL hash (JS-side), falling back to the
 * PHP-localized value (which cannot read fragments) or 'chat'.
 *
 * @return {string} Initial route string.
 */

function getInitialRoute() {
  const hash = window.location.hash;
  if (hash && hash.startsWith('#/')) {
    return hash.substring(2) || 'chat';
  }
  return window.gratisAiAgentData?.initialRoute || 'chat';
}

/**
 * Unified Admin App Component
 *
 * Main entry point for the unified admin SPA. Manages hash-based routing,
 * listens for hashchange events, and updates the document title on navigation.
 *
 * @return {JSX.Element} App element.
 */
function UnifiedAdminApp() {
  const [currentRoute, setCurrentRoute] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(getInitialRoute);
  const [notice, setNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);

  // Listen for hash changes.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    const handleHashChange = () => {
      const hash = window.location.hash;
      if (hash && hash.startsWith('#/')) {
        setCurrentRoute(hash.substring(2) || 'chat');
      } else {
        // Bare '#' or empty hash — default to chat.
        setCurrentRoute('chat');
      }
    };
    window.addEventListener('hashchange', handleHashChange);
    return () => window.removeEventListener('hashchange', handleHashChange);
  }, []);

  // Update document title based on route.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    const menuItems = window.gratisAiAgentData?.menuItems || [];
    const baseRoute = currentRoute.split('/')[0];
    const currentItem = menuItems.find(item => item.slug === baseRoute);
    if (currentItem) {
      document.title = `${currentItem.label} - AI Agent`;
    }

    // Sync WordPress admin submenu highlight with the current hash route.
    // WordPress marks the active submenu server-side, but since all our
    // submenu items share the same `page=gratis-ai-agent` query and only
    // differ by URL fragment (which the server never sees), only the first
    // item is ever highlighted. Update the `current` class client-side.
    const parentMenu = document.getElementById('toplevel_page_gratis-ai-agent');
    if (parentMenu) {
      const links = parentMenu.querySelectorAll('.wp-submenu a');
      links.forEach(link => {
        const href = decodeURIComponent(link.getAttribute('href') || '');
        const li = link.parentElement;
        let isCurrent = false;
        if (baseRoute === 'chat') {
          isCurrent = /[?&]page=gratis-ai-agent$/.test(href) && !href.includes('#');
        } else {
          isCurrent = href.endsWith('#/' + baseRoute);
        }
        if (isCurrent) {
          link.classList.add('current');
          li?.classList.add('current');
        } else {
          link.classList.remove('current');
          li?.classList.remove('current');
        }
      });
    }
  }, [currentRoute]);
  const appContext = {
    currentRoute,
    setCurrentRoute,
    showNotice: (status, message) => setNotice({
      status,
      message
    }),
    clearNotice: () => setNotice(null)
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_context__WEBPACK_IMPORTED_MODULE_4__.AppProvider, {
    value: appContext,
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
      className: "gratis-ai-agent-unified-admin",
      children: [notice && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
        status: notice.status,
        isDismissible: true,
        onRemove: () => setNotice(null),
        className: "gratis-ai-admin-notice",
        children: notice.message
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
        className: "gratis-ai-admin-layout",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("main", {
          className: "gratis-ai-admin-main",
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_router__WEBPACK_IMPORTED_MODULE_3__["default"], {
            route: currentRoute
          })
        })
      })]
    })
  });
}
const container = document.getElementById('gratis-ai-agent-root');
if (container) {
  const root = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createRoot)(container);
  root.render(/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(UnifiedAdminApp, {}));
}

/***/ },

/***/ "./src/unified-admin/router.js"
/*!*************************************!*\
  !*** ./src/unified-admin/router.js ***!
  \*************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Router)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _routes_chat__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./routes/chat */ "./src/unified-admin/routes/chat.js");
/* harmony import */ var _routes_abilities__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./routes/abilities */ "./src/unified-admin/routes/abilities.js");
/* harmony import */ var _routes_changes__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./routes/changes */ "./src/unified-admin/routes/changes.js");
/* harmony import */ var _routes_settings__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./routes/settings */ "./src/unified-admin/routes/settings.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);
/**
 * WordPress dependencies
 */


/**
 * Internal dependencies
 */





/**
 * Router Component
 *
 * Renders the appropriate route based on the current hash path.
 *
 * @param {Object} props       Component props.
 * @param {string} props.route Current route.
 * @return {JSX.Element} Route component.
 */

function Router({
  route
}) {
  const routeParts = (route || '').split('/');
  const mainRoute = routeParts[0];
  const subRoute = routeParts.slice(1).join('/') || null;
  switch (mainRoute) {
    case 'chat':
    case '':
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_routes_chat__WEBPACK_IMPORTED_MODULE_1__["default"], {});
    case 'abilities':
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_routes_abilities__WEBPACK_IMPORTED_MODULE_2__["default"], {});
    case 'changes':
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_routes_changes__WEBPACK_IMPORTED_MODULE_3__["default"], {});
    case 'settings':
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_routes_settings__WEBPACK_IMPORTED_MODULE_4__["default"], {
        subRoute: subRoute
      });
    default:
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
        className: "gratis-ai-agent-route-not-found",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("h2", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Page Not Found', 'gratis-ai-agent')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('The requested page could not be found.', 'gratis-ai-agent')
        })]
      });
  }
}

/***/ },

/***/ "./src/unified-admin/routes/abilities.js"
/*!***********************************************!*\
  !*** ./src/unified-admin/routes/abilities.js ***!
  \***********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ AbilitiesRoute)
/* harmony export */ });
/* harmony import */ var _abilities_explorer_abilities_explorer_app__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../abilities-explorer/abilities-explorer-app */ "./src/abilities-explorer/abilities-explorer-app.js");
/* harmony import */ var _abilities_explorer_style_css__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../abilities-explorer/style.css */ "./src/abilities-explorer/style.css");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);
/**
 * Internal dependencies
 */



/**
 * Abilities Route Component
 *
 * Renders the Abilities Explorer within the unified admin SPA.
 *
 * @return {JSX.Element} Abilities route element.
 */

function AbilitiesRoute() {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
    className: "gratis-ai-agent-route gratis-ai-agent-route-abilities",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_abilities_explorer_abilities_explorer_app__WEBPACK_IMPORTED_MODULE_0__["default"], {})
  });
}

/***/ },

/***/ "./src/unified-admin/routes/changes.js"
/*!*********************************************!*\
  !*** ./src/unified-admin/routes/changes.js ***!
  \*********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ ChangesRoute)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);
/**
 * WordPress dependencies
 */



/**
 * Changes Route Component
 *
 * @return {JSX.Element} Changes route element.
 */

function ChangesRoute() {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
    className: "gratis-ai-agent-route gratis-ai-agent-route-changes",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Card, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardHeader, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("h2", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Changes', 'gratis-ai-agent')
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardBody, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('View and manage changes made by the AI agent.', 'gratis-ai-agent')
        })
      })]
    })
  });
}

/***/ },

/***/ "./src/unified-admin/routes/chat.js"
/*!******************************************!*\
  !*** ./src/unified-admin/routes/chat.js ***!
  \******************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ ChatRoute)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);
/**
 * WordPress dependencies
 */




/**
 * Chat Route Component
 *
 * Mounts the AdminPageApp chat UI into a dedicated container.
 * The mount API (`window.gratisAiAgentChat`) is exposed by the admin-page
 * bundle (build/admin-page.js), which is enqueued after unified-admin.js.
 * Because the two bundles load asynchronously, the API may not be defined
 * when this component first mounts. We poll with a short interval until the
 * API becomes available, then call mount() exactly once.
 *
 * The mount API must expose an `unmount()` method so React's cleanup
 * lifecycle is respected — never clear the container via `innerHTML = ''`,
 * which bypasses React's unmount hooks and leaks subscriptions and event
 * handlers.
 *
 * @return {JSX.Element} Chat route element.
 */

function ChatRoute() {
  const containerRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
  // Track whether mount() has been called so we don't call it twice.
  const mountedRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(false);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    const container = containerRef.current;
    if (!container) {
      return;
    }

    /**
     * Attempt to mount the chat app. Returns true if mount succeeded.
     *
     * @return {boolean} Whether the mount API was available and called.
     */
    function tryMount() {
      if (window.gratisAiAgentChat && typeof window.gratisAiAgentChat.mount === 'function' && !mountedRef.current) {
        mountedRef.current = true;
        window.gratisAiAgentChat.mount(container);
        return true;
      }
      return false;
    }

    // Try immediately — the API may already be defined if admin-page.js
    // loaded synchronously before this effect ran.
    if (tryMount()) {
      return () => {
        if (mountedRef.current && window.gratisAiAgentChat && typeof window.gratisAiAgentChat.unmount === 'function') {
          window.gratisAiAgentChat.unmount(container);
        }
        mountedRef.current = false;
      };
    }

    // Poll every 50 ms for up to 30 s waiting for admin-page.js to load
    // and expose window.gratisAiAgentChat. This handles the race condition
    // where unified-admin.js renders ChatRoute before admin-page.js has
    // finished executing (both are loaded as async scripts).
    // 30 s matches the goToAgentPage() wait timeout in tests/e2e/utils/wp-admin.js
    // so the chat panel always has a chance to mount before the test times out.
    let intervalId = setInterval(() => {
      if (tryMount()) {
        clearInterval(intervalId);
        intervalId = null;
      }
    }, 50);

    // Safety timeout: stop polling after 30 s to avoid an infinite loop.
    const timeoutId = setTimeout(() => {
      if (intervalId) {
        clearInterval(intervalId);
        intervalId = null;
        // Log a warning to help diagnose a missing admin-page bundle.
        // eslint-disable-next-line no-console
        console.warn('[Gratis AI Agent] ChatRoute: window.gratisAiAgentChat.mount() not available after 30s. ' + 'Ensure build/admin-page.js is enqueued.');
      }
    }, 30_000);

    // Cleanup: stop polling and unmount the chat app.
    return () => {
      if (intervalId) {
        clearInterval(intervalId);
      }
      clearTimeout(timeoutId);
      if (mountedRef.current && window.gratisAiAgentChat && typeof window.gratisAiAgentChat.unmount === 'function') {
        window.gratisAiAgentChat.unmount(container);
        mountedRef.current = false;
      }
    };
  }, []);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
    className: "gratis-ai-agent-route gratis-ai-agent-route-chat",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Card, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardHeader, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("h2", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Chat', 'gratis-ai-agent')
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardBody, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
          ref: containerRef,
          id: "gratis-ai-agent-chat-container",
          className: "gratis-ai-agent-chat-container",
          style: {
            minHeight: '500px'
          }
        })
      })]
    })
  });
}

/***/ },

/***/ "./src/unified-admin/routes/settings.js"
/*!**********************************************!*\
  !*** ./src/unified-admin/routes/settings.js ***!
  \**********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ SettingsRoute)
/* harmony export */ });
/* harmony import */ var _settings_page_settings_app__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../settings-page/settings-app */ "./src/settings-page/settings-app.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);
/**
 * Internal dependencies
 */


/**
 * Settings Route Component
 *
 * Thin wrapper around SettingsApp — the outer General/Providers/Advanced tab
 * set was redundant with the inner tab bar and has been removed. Provider API
 * keys are now configured from the network-level Connectors page (WP Multisite
 * WaaS 7+), so there is no in-app Providers tab either.
 *
 * @return {JSX.Element} Settings route element.
 */

function SettingsRoute() {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
    className: "gratis-ai-agent-route gratis-ai-agent-route-settings",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_settings_page_settings_app__WEBPACK_IMPORTED_MODULE_0__["default"], {})
  });
}

/***/ },

/***/ "./src/abilities-explorer/style.css"
/*!******************************************!*\
  !*** ./src/abilities-explorer/style.css ***!
  \******************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./src/settings-page/style.css"
/*!*************************************!*\
  !*** ./src/settings-page/style.css ***!
  \*************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./src/unified-admin/style.css"
/*!*************************************!*\
  !*** ./src/unified-admin/style.css ***!
  \*************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "react/jsx-runtime"
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
(module) {

module.exports = window["ReactJSXRuntime"];

/***/ },

/***/ "@wordpress/api-fetch"
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
(module) {

module.exports = window["wp"]["apiFetch"];

/***/ },

/***/ "@wordpress/components"
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
(module) {

module.exports = window["wp"]["components"];

/***/ },

/***/ "@wordpress/data"
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["data"];

/***/ },

/***/ "@wordpress/element"
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
(module) {

module.exports = window["wp"]["element"];

/***/ },

/***/ "@wordpress/i18n"
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["i18n"];

/***/ },

/***/ "@wordpress/primitives"
/*!************************************!*\
  !*** external ["wp","primitives"] ***!
  \************************************/
(module) {

module.exports = window["wp"]["primitives"];

/***/ },

/***/ "./node_modules/@wordpress/icons/build-module/library/backup.mjs"
/*!***********************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/backup.mjs ***!
  \***********************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ backup_default)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
// packages/icons/src/library/backup.tsx


var backup_default = /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, { d: "M5.5 12h1.75l-2.5 3-2.5-3H4a8 8 0 113.134 6.35l.907-1.194A6.5 6.5 0 105.5 12zm9.53 1.97l-2.28-2.28V8.5a.75.75 0 00-1.5 0V12a.747.747 0 00.218.529l1.282-.84-1.28.842 2.5 2.5a.75.75 0 101.06-1.061z" }) });

//# sourceMappingURL=backup.mjs.map


/***/ },

/***/ "./node_modules/@wordpress/icons/build-module/library/pencil.mjs"
/*!***********************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/pencil.mjs ***!
  \***********************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ pencil_default)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
// packages/icons/src/library/pencil.tsx


var pencil_default = /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, { d: "m19 7-3-3-8.5 8.5-1 4 4-1L19 7Zm-7 11.5H5V20h7v-1.5Z" }) });

//# sourceMappingURL=pencil.mjs.map


/***/ },

/***/ "./node_modules/@wordpress/icons/build-module/library/plus.mjs"
/*!*********************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/plus.mjs ***!
  \*********************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ plus_default)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
// packages/icons/src/library/plus.tsx


var plus_default = /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, { d: "M11 12.5V17.5H12.5V12.5H17.5V11H12.5V6H11V11H6V12.5H11Z" }) });

//# sourceMappingURL=plus.mjs.map


/***/ },

/***/ "./node_modules/@wordpress/icons/build-module/library/seen.mjs"
/*!*********************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/seen.mjs ***!
  \*********************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ seen_default)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
// packages/icons/src/library/seen.tsx


var seen_default = /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, { d: "M3.99961 13C4.67043 13.3354 4.6703 13.3357 4.67017 13.3359L4.67298 13.3305C4.67621 13.3242 4.68184 13.3135 4.68988 13.2985C4.70595 13.2686 4.7316 13.2218 4.76695 13.1608C4.8377 13.0385 4.94692 12.8592 5.09541 12.6419C5.39312 12.2062 5.84436 11.624 6.45435 11.0431C7.67308 9.88241 9.49719 8.75 11.9996 8.75C14.502 8.75 16.3261 9.88241 17.5449 11.0431C18.1549 11.624 18.6061 12.2062 18.9038 12.6419C19.0523 12.8592 19.1615 13.0385 19.2323 13.1608C19.2676 13.2218 19.2933 13.2686 19.3093 13.2985C19.3174 13.3135 19.323 13.3242 19.3262 13.3305L19.3291 13.3359C19.3289 13.3357 19.3288 13.3354 19.9996 13C20.6704 12.6646 20.6703 12.6643 20.6701 12.664L20.6697 12.6632L20.6688 12.6614L20.6662 12.6563L20.6583 12.6408C20.6517 12.6282 20.6427 12.6108 20.631 12.5892C20.6078 12.5459 20.5744 12.4852 20.5306 12.4096C20.4432 12.2584 20.3141 12.0471 20.1423 11.7956C19.7994 11.2938 19.2819 10.626 18.5794 9.9569C17.1731 8.61759 14.9972 7.25 11.9996 7.25C9.00203 7.25 6.82614 8.61759 5.41987 9.9569C4.71736 10.626 4.19984 11.2938 3.85694 11.7956C3.68511 12.0471 3.55605 12.2584 3.4686 12.4096C3.42484 12.4852 3.39142 12.5459 3.36818 12.5892C3.35656 12.6108 3.34748 12.6282 3.34092 12.6408L3.33297 12.6563L3.33041 12.6614L3.32948 12.6632L3.32911 12.664C3.32894 12.6643 3.32879 12.6646 3.99961 13ZM11.9996 16C13.9326 16 15.4996 14.433 15.4996 12.5C15.4996 10.567 13.9326 9 11.9996 9C10.0666 9 8.49961 10.567 8.49961 12.5C8.49961 14.433 10.0666 16 11.9996 16Z" }) });

//# sourceMappingURL=seen.mjs.map


/***/ },

/***/ "./node_modules/@wordpress/icons/build-module/library/trash.mjs"
/*!**********************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/trash.mjs ***!
  \**********************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ trash_default)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
// packages/icons/src/library/trash.tsx


var trash_default = /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, { fillRule: "evenodd", clipRule: "evenodd", d: "M12 5.5A2.25 2.25 0 0 0 9.878 7h4.244A2.251 2.251 0 0 0 12 5.5ZM12 4a3.751 3.751 0 0 0-3.675 3H5v1.5h1.27l.818 8.997a2.75 2.75 0 0 0 2.739 2.501h4.347a2.75 2.75 0 0 0 2.738-2.5L17.73 8.5H19V7h-3.325A3.751 3.751 0 0 0 12 4Zm4.224 4.5H7.776l.806 8.861a1.25 1.25 0 0 0 1.245 1.137h4.347a1.25 1.25 0 0 0 1.245-1.137l.805-8.861Z" }) });

//# sourceMappingURL=trash.mjs.map


/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"unified-admin": 0,
/******/ 			"./style-unified-admin": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = globalThis["webpackChunkgratis_ai_agent"] = globalThis["webpackChunkgratis_ai_agent"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["./style-unified-admin"], () => (__webpack_require__("./src/unified-admin/index.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=unified-admin.js.map