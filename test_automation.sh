#!/bin/bash

# Scheduled Automation Testing Script
# Run from: /home/rev/Development/projects/erp
# Usage: bash test_automation.sh

set -e

BASEDIR=$(cd "$(dirname "$0")" && pwd)
echo "Base directory: $BASEDIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Helper functions
print_header() {
    echo -e "\n${BLUE}═══════════════════════════════════════${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}═══════════════════════════════════════${NC}\n"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

# Test 1: Check PHP version
print_header "Test 1: Environment Check"

if ! command -v php &> /dev/null; then
    print_error "PHP not found"
    exit 1
fi
PHP_VERSION=$(php -v | head -n 1)
print_success "PHP available: $PHP_VERSION"

# Test 2: Check critical files exist
print_header "Test 2: File Existence Check"

REQUIRED_FILES=(
    "modules/ramos/helpers/ramos_automation_helper.php"
    "modules/ramos/controllers/Automation.php"
    "modules/ramos/controllers/Settings.php"
    "modules/ramos/migrations/001_add_scheduled_automation_config.php"
    "modules/ramos/ramos.php"
    "application/config/app-config.php"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$BASEDIR/$file" ]; then
        print_success "Found: $file"
    else
        print_error "Missing: $file"
    fi
done

# Test 3: Run PHP syntax check
print_header "Test 3: PHP Syntax Check"

for file in "${REQUIRED_FILES[@]}"; do
    if [[ $file == *.php ]]; then
        if php -l "$BASEDIR/$file" > /dev/null 2>&1; then
            print_success "Syntax OK: $file"
        else
            print_error "Syntax error: $file"
            php -l "$BASEDIR/$file"
        fi
    fi
done

# Test 4: Check database connection
print_header "Test 4: Database Connection Check"

DB_CONFIG=$(grep -E "^define\('APP_DB_" "$BASEDIR/application/config/app-config.php" | head -1)
if [ -n "$DB_CONFIG" ]; then
    print_success "Database config found"
else
    print_error "Database config not found in app-config.php"
fi

# Test 5: Check migration file
print_header "Test 5: Migration File Check"

MIGRATION_FILE="$BASEDIR/modules/ramos/migrations/001_add_scheduled_automation_config.php"
if grep -q "add_option.*ramos_automation_schedule" "$MIGRATION_FILE"; then
    print_success "Migration contains required options"
else
    print_error "Migration missing required options"
fi

# Test 6: Check helper functions
print_header "Test 6: Helper Functions Check"

HELPER_FILE="$BASEDIR/modules/ramos/helpers/ramos_automation_helper.php"
REQUIRED_FUNCTIONS=(
    "ramos_execute_automation"
    "ramos_generate_routes_for_today"
    "ramos_should_run_scheduled_automation"
)

for func in "${REQUIRED_FUNCTIONS[@]}"; do
    if grep -q "function $func" "$HELPER_FILE"; then
        print_success "Function found: $func()"
    else
        print_error "Function missing: $func()"
    fi
done

# Test 7: Check controller modifications
print_header "Test 7: Controller Modifications Check"

AUTOMATION_CONTROLLER="$BASEDIR/modules/ramos/controllers/Automation.php"
if grep -q "ramos_execute_automation" "$AUTOMATION_CONTROLLER"; then
    print_success "Automation controller uses helper"
else
    print_error "Automation controller not refactored"
fi

# Test 8: Check Settings controller exists
print_header "Test 8: Settings Controller Check"

SETTINGS_CONTROLLER="$BASEDIR/modules/ramos/controllers/Settings.php"
if grep -q "class Settings" "$SETTINGS_CONTROLLER"; then
    print_success "Settings controller exists"
    if grep -q "automation_schedule" "$SETTINGS_CONTROLLER"; then
        print_success "Settings controller has automation_schedule method"
    fi
else
    print_error "Settings controller malformed"
fi

# Test 9: Check cron hook registration
print_header "Test 9: Cron Hook Registration Check"

RAMOS_BOOTSTRAP="$BASEDIR/modules/ramos/ramos.php"
if grep -q "hooks()->add_action('after_cron_run'" "$RAMOS_BOOTSTRAP"; then
    print_success "Cron hook registered in ramos.php"
else
    print_error "Cron hook not registered"
fi

# Test 10: Check language strings
print_header "Test 10: Language File Check"

LANG_FILE="$BASEDIR/modules/ramos/language/english/ramos_lang.php"
LANG_STRINGS=(
    "ramos_settings_automation_schedule_title"
    "ramos_settings_automation_enabled"
    "ramos_settings_saved_successfully"
)

for str in "${LANG_STRINGS[@]}"; do
    if grep -q "'$str'" "$LANG_FILE"; then
        print_success "Language string found: $str"
    else
        print_error "Language string missing: $str"
    fi
done

# Summary
print_header "Test Summary"

print_info "All file and syntax checks complete!"
print_info ""
print_warning "Next steps:"
echo "1. Run migration: php index.php migrate"
echo "2. Access settings: /admin/ramos/settings/automation_schedule"
echo "3. Configure automation hours"
echo "4. Trigger cron: curl http://localhost:8080/cron/YOUR_CRON_KEY"
echo "5. Check activity log for [RAMOS CRON] entries"
echo ""
print_success "Testing script complete!"
