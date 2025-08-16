# E2E Tests for Menu Maker - Recipes Module

This directory contains comprehensive End-to-End (E2E) tests for the Recipe functionality of the Menu Maker application, built with Playwright and aligned with the project's test plan.

## 📁 Test Structure

```
tests/e2e/
├── README.md                           # This file
├── recipes.spec.ts                     # Main recipe CRUD and functionality tests
├── recipes-performance.spec.ts         # Performance and pagination tests
├── recipes-components.spec.ts          # Component-specific interaction tests
├── recipes-accessibility.spec.ts       # Accessibility and UX tests
├── helpers/
│   └── recipe-helpers.ts               # Shared test utilities and helpers
└── login.spec.ts                       # Authentication tests (existing)
```

## 🎯 Test Coverage

### Main Recipe Tests (`recipes.spec.ts`)
- **Navigation and Access Control** (E2E-001)
  - Unauthenticated user redirection
  - Authenticated user access
  - Navigation menu integration

- **Recipe CRUD Operations** (E2E-002)
  - Create new recipes with form validation
  - View recipe details
  - Edit existing recipes
  - Delete recipes with confirmation modal

- **Search and Filtering** (E2E-003)
  - Search functionality with debouncing
  - Category filtering
  - Reset filters functionality

- **Sorting and Pagination** (E2E-004)
  - Column sorting (name, category, calories, created date)
  - Pagination navigation
  - Multi-page data handling

- **User Isolation and Authorization** (E2E-005)
  - User can only see their own recipes
  - Authorization checks for recipe access
  - Security boundary testing

- **Empty States and Error Handling** (E2E-006)
  - Empty state display
  - No search results handling
  - Form validation errors

- **Accessibility and UX** (E2E-007)
  - Keyboard navigation
  - ARIA labels and roles
  - Focus management

### Performance Tests (`recipes-performance.spec.ts`)
- **Pagination Performance** (PERF-001)
  - Large dataset handling (50+ recipes)
  - Search performance with large datasets
  - Category filtering performance

- **Sorting Performance** (PERF-002)
  - Column sorting with large datasets
  - Combined filters and sorting performance

- **Memory and Resource Usage** (PERF-003)
  - Page memory stability
  - Component lifecycle memory management

- **API Response Times** (PERF-004)
  - Network request performance monitoring

### Component Tests (`recipes-components.spec.ts`)
- **RecipesToolbar Component** (COMP-001)
  - Search input with debouncing
  - Category dropdown filtering
  - Reset filters button
  - Create recipe button navigation

- **RecipesTable Component** (COMP-002)
  - Table data display
  - Sortable headers
  - Recipe row actions (view, edit, delete)
  - Empty state display

- **ConfirmDeleteModal Component** (COMP-003)
  - Modal appearance and content
  - Cancel functionality
  - Backdrop click handling
  - Confirm deletion
  - Keyboard navigation (Escape, Tab)

- **Pagination Component** (COMP-004)
  - Pagination display
  - Navigation functionality
  - Filter state maintenance

- **Responsive Design** (COMP-005)
  - Mobile responsiveness
  - Tablet responsiveness

### Accessibility Tests (`recipes-accessibility.spec.ts`)
- **Keyboard Navigation** (A11Y-001)
  - Tab navigation through page elements
  - Recipe row action navigation
  - Search input keyboard functionality
  - Sorting with keyboard

- **ARIA Attributes and Semantic HTML** (A11Y-002)
  - Table structure with proper roles
  - Form controls with labels
  - Modal ARIA attributes
  - Sortable headers with ARIA sort

- **Focus Management** (A11Y-003)
  - Delete modal focus trap
  - Page navigation focus management
  - Focus visible indicators

- **Screen Reader Support** (A11Y-004)
  - Proper heading structure
  - Status message announcements
  - Data table structure
  - Loading state announcements

- **Color and Contrast** (A11Y-005)
  - Text contrast verification
  - Interactive element contrast

- **Error Handling and Validation** (A11Y-006)
  - Accessible form validation errors
  - Error state recovery

- **Mobile Accessibility** (A11Y-007)
  - Touch target sizes
  - Mobile screen reader compatibility

## 🚀 Running Tests

### Prerequisites

1. **Install Dependencies**
   ```bash
   npm ci
   ```

2. **Install Playwright Browsers**
   ```bash
   npx playwright install --with-deps
   ```

3. **Start Application**
   ```bash
   ./vendor/bin/sail up -d
   ```

4. **Prepare Test Database**
   ```bash
   ./vendor/bin/sail artisan migrate:fresh --seed --env=testing
   ```

### Running E2E Tests

**All recipe tests:**
```bash
E2E_BASE_URL=http://localhost npm run test:e2e tests/e2e/recipes*.spec.ts
```

**Specific test files:**
```bash
# Main functionality tests
E2E_BASE_URL=http://localhost npx playwright test tests/e2e/recipes.spec.ts

# Performance tests
E2E_BASE_URL=http://localhost npx playwright test tests/e2e/recipes-performance.spec.ts

# Component tests
E2E_BASE_URL=http://localhost npx playwright test tests/e2e/recipes-components.spec.ts

# Accessibility tests
E2E_BASE_URL=http://localhost npx playwright test tests/e2e/recipes-accessibility.spec.ts
```

**Specific test suites:**
```bash
# CRUD operations only
E2E_BASE_URL=http://localhost npx playwright test tests/e2e/recipes.spec.ts --grep "Recipe CRUD Operations"

# Performance tests only
E2E_BASE_URL=http://localhost npx playwright test tests/e2e/recipes-performance.spec.ts --grep "Pagination Performance"

# Accessibility tests only
E2E_BASE_URL=http://localhost npx playwright test tests/e2e/recipes-accessibility.spec.ts --grep "Keyboard Navigation"
```

**Run in headed mode (visible browser):**
```bash
E2E_BASE_URL=http://localhost npx playwright test tests/e2e/recipes.spec.ts --headed
```

**Run with debugging:**
```bash
E2E_BASE_URL=http://localhost npx playwright test tests/e2e/recipes.spec.ts --debug
```

### Parallel Execution

Playwright supports parallel test execution:

```bash
# Run tests in parallel (default)
E2E_BASE_URL=http://localhost npx playwright test tests/e2e/recipes*.spec.ts

# Control parallel workers
E2E_BASE_URL=http://localhost npx playwright test tests/e2e/recipes*.spec.ts --workers=2
```

## 🔧 Configuration

### Environment Variables

- `E2E_BASE_URL`: Base URL for the application (default: `http://localhost`)
- `E2E_SKIP_WEBSERVER`: Skip waiting for web server if already running

### Playwright Configuration

Tests use the main `playwright.config.ts` with:
- **Browsers**: Chromium, Firefox
- **Timeout**: 60 seconds per test
- **Expect timeout**: 5 seconds
- **Retries**: Configurable per environment
- **Screenshots**: On failure
- **Video**: On first retry

## 🧪 Test Data and Patterns

### User Management
- Each test creates isolated user accounts
- Random email generation prevents conflicts
- Users are scoped to their own data

### Recipe Data
- Sample recipes with varied categories (breakfast, dinner, supper)
- Different calorie values for sorting tests
- Multiple ingredients for comprehensive testing

### Test Isolation
- Tests are designed to be independent
- No shared state between tests
- Database reset strategies for clean starts

### Error Handling
- Network error simulation
- Form validation testing
- Graceful degradation verification

## 📊 Performance Benchmarks

### Expected Performance Metrics
- **Page Load Time**: < 3 seconds for recipes index
- **Search Response**: < 1 second with debouncing
- **Pagination Navigation**: < 2 seconds between pages
- **Sorting Operations**: < 2 seconds for up to 50 recipes
- **Modal Operations**: < 500ms for open/close

### Large Dataset Testing
- Tests with 50+ recipes for pagination
- Performance degradation monitoring
- Memory usage stability checks

## ♿ Accessibility Standards

Tests verify compliance with:
- **WCAG 2.1 AA** guidelines
- **Keyboard Navigation** support
- **Screen Reader** compatibility
- **Focus Management** best practices
- **Color Contrast** requirements
- **Touch Target** sizing (mobile)

## 🐛 Debugging Tests

### Common Issues and Solutions

1. **Test Timeouts**
   - Check if application is running on correct URL
   - Verify database is seeded properly
   - Increase timeout for slow operations

2. **Element Not Found**
   - Check if selectors match current UI
   - Verify test data is created correctly
   - Use `.waitFor()` for dynamic content

3. **Flaky Tests**
   - Add proper waits for network requests
   - Use `page.waitForLoadState('networkidle')`
   - Implement retry logic for transient failures

4. **Performance Test Failures**
   - Check system resources during test run
   - Verify test environment matches expectations
   - Adjust performance thresholds if needed

### Debug Mode

Run tests with `--debug` flag to:
- Step through tests manually
- Inspect page state
- Modify test code on the fly
- Use browser developer tools

```bash
E2E_BASE_URL=http://localhost npx playwright test tests/e2e/recipes.spec.ts --debug
```

## 📈 Continuous Integration

### GitHub Actions Integration

Tests are designed to run in CI with:
- Headless browser execution
- JUnit report generation
- Screenshot artifacts on failure
- Test result summaries

### Test Reports

Generate HTML reports:
```bash
npx playwright show-report
```

## 🔮 Future Enhancements

### Planned Test Additions
- **Visual Regression Testing** with screenshots
- **API Integration Testing** with network mocking
- **Cross-Browser Compatibility** testing
- **Mobile Device Testing** with device emulation
- **Internationalization Testing** for Polish/English content

### Performance Monitoring
- **Real User Monitoring** integration
- **Performance Budget** enforcement
- **Core Web Vitals** tracking

## 📝 Contributing

When adding new recipe E2E tests:

1. **Follow naming conventions**: Use descriptive test IDs (E2E-XXX, COMP-XXX, A11Y-XXX)
2. **Use helper functions**: Leverage `recipe-helpers.ts` for common operations
3. **Maintain test isolation**: Each test should be independent
4. **Document new patterns**: Update this README for new test patterns
5. **Consider accessibility**: Include accessibility checks for new features
6. **Performance awareness**: Add performance tests for new heavy operations

### Test ID Conventions
- `E2E-XXX`: End-to-end functionality tests
- `COMP-XXX`: Component-specific tests
- `PERF-XXX`: Performance tests
- `A11Y-XXX`: Accessibility tests
- `SEC-XXX`: Security tests (if applicable)

### Helper Function Guidelines
- Keep helpers generic and reusable
- Add proper TypeScript types
- Include JSDoc documentation
- Handle error cases gracefully
- Return meaningful data for assertions
