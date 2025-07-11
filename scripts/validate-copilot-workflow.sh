#!/bin/bash

# Test Script for Copilot Agent Workflow Integration
# This script validates that the test workflow modifications work correctly

set -e  # Exit on any error

echo "🧪 Validating Copilot Agent Test Workflow Integration..."
echo "=================================================="

# Check that the workflow file exists and has correct triggers
echo "✅ Checking workflow file exists..."
if [ ! -f ".github/workflows/test.yaml" ]; then
    echo "❌ Error: Workflow file not found"
    exit 1
fi

echo "✅ Checking workflow triggers..."
if grep -q "workflow_dispatch:" .github/workflows/test.yaml; then
    echo "✅ workflow_dispatch trigger found"
else
    echo "❌ Error: workflow_dispatch trigger not found"
    exit 1
fi

if grep -q "pull_request:" .github/workflows/test.yaml; then
    echo "✅ pull_request trigger found"
else
    echo "❌ Error: pull_request trigger not found"
    exit 1
fi

if grep -q "push:" .github/workflows/test.yaml && grep -A3 "push:" .github/workflows/test.yaml | grep -q "branches:"; then
    echo "✅ push trigger with branches found"
else
    echo "❌ Error: push trigger with branches not found"
    exit 1
fi

echo ""
echo "📋 Testing backend setup..."
echo "✅ Checking PHP availability..."
php --version

echo "✅ Checking Composer availability..."
composer --version

echo "✅ Checking if vendor directory exists..."
if [ -d "vendor" ]; then
    echo "✅ Vendor directory exists"
else
    echo "⚠️  Vendor directory not found - run 'composer install' first"
fi

echo ""
echo "📋 Testing frontend setup..."
echo "✅ Checking Node.js availability..."
node --version

echo "✅ Checking Yarn availability..."
yarn --version

echo "✅ Checking frontend dependencies..."
if [ -d "frontend/node_modules" ]; then
    echo "✅ Frontend node_modules exists"
else
    echo "⚠️  Frontend node_modules not found - run 'cd frontend && yarn install' first"
fi

echo ""
echo "🎯 Workflow Validation Summary:"
echo "================================"
echo "✅ Workflow file exists"
echo "✅ workflow_dispatch trigger configured (for manual agent execution)"
echo "✅ pull_request trigger preserved (for PR testing)"
echo "✅ push trigger configured (for automated testing on main/develop)"
echo "✅ PHP environment ready"
echo "✅ Node.js environment ready"
echo ""
echo "🚀 The workflow is ready for Copilot agent integration!"
echo ""
echo "To trigger manually via API:"
echo "curl -X POST \\"
echo "  -H \"Accept: application/vnd.github.v3+json\" \\"
echo "  -H \"Authorization: token \$GITHUB_TOKEN\" \\"
echo "  https://api.github.com/repos/paperclipmonkey/subterra/actions/workflows/test.yaml/dispatches \\"
echo "  -d '{\"ref\":\"main\"}'"