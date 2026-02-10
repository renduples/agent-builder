#!/usr/bin/env bash

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Agent Builder Test Runner${NC}\n"

# Check if WordPress test suite is installed
WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib}

if [ ! -d "$WP_TESTS_DIR" ]; then
    echo -e "${RED}WordPress test suite not found!${NC}"
    echo "Please run: bin/install-wp-tests.sh wordpress_test root '' localhost latest"
    exit 1
fi

# Parse arguments
SUITE="all"
COVERAGE=false
FILTER=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --unit)
            SUITE="unit"
            shift
            ;;
        --integration)
            SUITE="integration"
            shift
            ;;
        --coverage)
            COVERAGE=true
            shift
            ;;
        --filter)
            FILTER="$2"
            shift 2
            ;;
        --help)
            echo "Usage: ./tests/run-tests.sh [options]"
            echo ""
            echo "Options:"
            echo "  --unit         Run only unit tests"
            echo "  --integration  Run only integration tests"
            echo "  --coverage     Generate code coverage report"
            echo "  --filter <name> Run tests matching filter"
            echo "  --help         Show this help message"
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            exit 1
            ;;
    esac
done

# Build PHPUnit command
PHPUNIT_CMD="./vendor/bin/phpunit"

if [ "$SUITE" = "unit" ]; then
    PHPUNIT_CMD="$PHPUNIT_CMD --testsuite 'Unit Tests'"
    echo -e "${YELLOW}Running unit tests...${NC}\n"
elif [ "$SUITE" = "integration" ]; then
    PHPUNIT_CMD="$PHPUNIT_CMD --testsuite 'Integration Tests'"
    echo -e "${YELLOW}Running integration tests...${NC}\n"
else
    echo -e "${YELLOW}Running all tests...${NC}\n"
fi

if [ "$COVERAGE" = true ]; then
    PHPUNIT_CMD="$PHPUNIT_CMD --coverage-html coverage-report --coverage-text"
    echo -e "${YELLOW}Code coverage enabled${NC}\n"
fi

if [ -n "$FILTER" ]; then
    PHPUNIT_CMD="$PHPUNIT_CMD --filter $FILTER"
    echo -e "${YELLOW}Filter: $FILTER${NC}\n"
fi

# Run tests
eval $PHPUNIT_CMD
TEST_EXIT_CODE=$?

# Show results
echo ""
if [ $TEST_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
    
    if [ "$COVERAGE" = true ]; then
        echo -e "${GREEN}Coverage report: coverage-report/index.html${NC}"
    fi
else
    echo -e "${RED}✗ Some tests failed${NC}"
fi

exit $TEST_EXIT_CODE
