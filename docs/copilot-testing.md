# Copilot Agent Testing Integration

This document describes how the test workflow has been modified to support Copilot coding agent integration.

## Changes Made

The test workflow (`.github/workflows/test.yaml`) has been updated to support multiple trigger types:

1. **Manual Execution** (`workflow_dispatch`): Allows Copilot agents to trigger tests manually
2. **Push Events**: Automatically runs tests on pushes to main and develop branches
3. **Pull Requests**: Maintains existing behavior for PR testing

## Original Workflow Triggers

```yaml
on:
  pull_request:
```

## Updated Workflow Triggers

```yaml
on:
  pull_request:
  workflow_dispatch:
  push:
    branches:
      - main
      - develop
```

## Test Coverage

The workflow includes comprehensive testing for both backend and frontend:

### Backend (Laravel/PHPUnit)
- PHP 8.4 environment setup
- Composer dependency installation
- SQLite database setup and migrations
- PHPUnit test execution
- **Result**: ✅ 5 tests, 11 assertions passed

### Frontend (Vue.js/Vitest)
- Node.js 22 environment setup
- Yarn package installation
- Vitest test execution
- Production build validation
- **Result**: ✅ 24 tests across 6 test files passed

## How Copilot Agents Can Use This

### Manual Trigger via API
Copilot agents can trigger the workflow using the GitHub API:

```bash
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token $GITHUB_TOKEN" \
  https://api.github.com/repos/paperclipmonkey/subterra/actions/workflows/test.yaml/dispatches \
  -d '{"ref":"main"}'
```

### Automatic Triggers
Tests will automatically run when:
- Code is pushed to main or develop branches
- Pull requests are opened, updated, or synchronized

## Benefits for Copilot Agents

1. **On-Demand Testing**: Agents can run tests at any time to validate changes
2. **Automated Validation**: Tests run automatically on relevant branch pushes
3. **Full Stack Coverage**: Both backend API and frontend components are tested
4. **Environment Consistency**: Tests run in the same environment as CI/CD

## Backward Compatibility

All existing functionality is preserved:
- PR-based testing continues to work
- All test steps and dependencies remain unchanged
- No breaking changes to the existing workflow