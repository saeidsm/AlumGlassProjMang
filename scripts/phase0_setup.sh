#!/bin/bash
# ==============================================================
# AlumGlass — Phase 0: Initial Setup & Emergency Fixes
# ==============================================================
# This script is meant to be run by Claude Code step by step.
# Each section can be run independently.
# ==============================================================

set -e

REPO_NAME="AlumGlassProjMang"
GITHUB_USER="YOUR_GITHUB_USERNAME"  # Replace with actual username
ZIP_PATH="./Alumglass-ir.zip"       # Path to the uploaded zip

echo "=========================================="
echo "Phase 0: AlumGlass Setup & Emergency Fixes"
echo "=========================================="

# ----------------------------------------------------------
# STEP 1: Create GitHub repo and initialize
# ----------------------------------------------------------
step1_create_repo() {
    echo ""
    echo ">>> Step 1: Creating GitHub repository..."
    
    # Create repo via GitHub CLI (gh) or API
    gh repo create "$GITHUB_USER/$REPO_NAME" --private --description "AlumGlass Engineering Facade Project Management Dashboard" || true
    
    # Clone
    git clone "https://github.com/$GITHUB_USER/$REPO_NAME.git"
    cd "$REPO_NAME"
    
    echo "✅ Repository created and cloned"
}

# ----------------------------------------------------------
# STEP 2: Extract zip and copy essential files
# ----------------------------------------------------------
step2_extract_and_clean() {
    echo ""
    echo ">>> Step 2: Extracting and cleaning..."
    
    # Extract
    unzip -o "$ZIP_PATH" -d ./temp_extract
    
    # Copy project files (exclude libraries we'll handle separately)
    cp -r ./temp_extract/Alumglass-ir/* ./
    
    # Clean up temp
    rm -rf ./temp_extract
    
    echo "✅ Files extracted"
}

# ----------------------------------------------------------
# STEP 3: Remove dangerous files
# ----------------------------------------------------------
step3_remove_dangerous() {
    echo ""
    echo ">>> Step 3: Removing dangerous files..."
    
    # Critical security removals
    rm -f info.php
    rm -f localhost.sql.txt
    
    echo "  ✅ Removed info.php"
    echo "  ✅ Removed localhost.sql.txt"
}

# ----------------------------------------------------------
# STEP 4: Remove copy/old/dead files
# ----------------------------------------------------------
step4_remove_dead_files() {
    echo ""
    echo ">>> Step 4: Removing copy/old/dead files..."
    
    # Admin old
    rm -f adminold.php
    
    # Ghom copies and old files
    rm -f "ghom/footer copy.php"
    rm -f "ghom/my_calendar copy.php"
    rm -f "ghom/Copy of verify_signatures.php"
    rm -f ghom/api/save_inspection_old.php
    rm -f ghom/api/get_element_data_old.php
    rm -f "ghom/api/get_cracks_for_plan copy.php"
    rm -f "ghom/api/store_public_key copy.php"
    rm -f ghom/api/saveinspectionoldcorrect.php
    rm -f ghom/api/getelementdataold.php
    rm -f ghom/dailycopy.php
    rm -f "ghom/workshop_report copy.php"
    rm -f ghom/reportold.php
    rm -f "ghom/contractor_batch_update copy.php"
    rm -f ghom/header_ghom1.php
    rm -f ghom/inspection_dashboard.new.php
    rm -f ghom/inspection_dashboard.new1php.php
    rm -f ghom/inspection_dashboard_diff.php
    rm -f ghom/viewer_diff.php
    rm -f "ghom/header_ins.php"
    rm -f "ghom/header_mobile.php"
    
    # Pardis copies
    rm -f "pardis/letters - Copy.php"
    rm -f "pardis/daily_report_submit - Copy.php"
    rm -f "pardis/packing_list_viewer copy.php"
    rm -f "pardis/zirsazi_api copy.php"
    rm -f "pardis/zirsazi_status copy.php"
    rm -f "pardis/meeting_minutes_form - Copy.php"
    rm -f "pardis/project_schedule copy.php"
    rm -f "pardis/daily_reports_dashboard_ps copy.php"
    rm -f pardis/daily_report_form_ps1.php
    rm -f "messages2 copy.php"
    
    # Count remaining
    REMAINING=$(find . -name "* copy*" -o -name "*Copy*" -o -name "*old*" | grep -v ".git" | grep -v "includes/mpdf\|includes/libraries" | wc -l)
    echo "  ✅ Dead files removed. Remaining suspicious files: $REMAINING"
}

# ----------------------------------------------------------
# STEP 5: Remove debug/test files
# ----------------------------------------------------------
step5_remove_debug() {
    echo ""
    echo ">>> Step 5: Removing debug/test files..."
    
    rm -f ghom/debug_test.php
    rm -f ghom/vv.php
    rm -f pardis/final_test.php
    rm -f pardis/test_telegram_proxy.php
    rm -f pardis/test_weather.php
    rm -f test_webhook.php
    
    # Remove log files from document root
    rm -f ghom/api/save_inspection_debug.log
    rm -f ghom/api/save_debug.log
    rm -f pardis/api/save_inspection_debug.log
    rm -rf pardis/api/logs/
    
    echo "  ✅ Debug/test files removed"
    echo "  ✅ Log files removed from document root"
}

# ----------------------------------------------------------
# STEP 6: Create .env.example and .gitignore
# ----------------------------------------------------------
step6_create_config() {
    echo ""
    echo ">>> Step 6: Creating configuration files..."
    
    # .gitignore and .env.example should already be copied from framework
    # Verify they exist
    test -f .gitignore && echo "  ✅ .gitignore exists" || echo "  ❌ .gitignore MISSING"
    test -f .env.example && echo "  ✅ .env.example exists" || echo "  ❌ .env.example MISSING"
}

# ----------------------------------------------------------
# STEP 7: Create directory structure
# ----------------------------------------------------------
step7_create_dirs() {
    echo ""
    echo ">>> Step 7: Creating directory structure..."
    
    mkdir -p logs
    mkdir -p docs
    mkdir -p includes
    
    # Create placeholder for logs dir (git doesn't track empty dirs)
    touch logs/.gitkeep
    
    echo "  ✅ Directory structure created"
}

# ----------------------------------------------------------
# STEP 8: Initial Git commit
# ----------------------------------------------------------
step8_git_commit() {
    echo ""
    echo ">>> Step 8: Initial Git commit..."
    
    git add -A
    git commit -m "chore(global): initial commit — cleaned codebase

Phase 0 Emergency Fixes:
- Removed info.php (phpinfo exposure)
- Removed localhost.sql.txt (database dump)
- Removed 34 copy/old/dead files
- Removed debug/test files
- Removed log files from document root
- Added .gitignore with proper exclusions
- Added .env.example template
- Added CLAUDE.md (development guide)
- Added docs/ (ARCHITECTURE, TECH_DEBT, SETUP, CHANGELOG)"
    
    git push origin main
    
    echo "  ✅ Initial commit pushed to GitHub"
}

# ----------------------------------------------------------
# VERIFICATION
# ----------------------------------------------------------
verify_phase0() {
    echo ""
    echo "=========================================="
    echo "Phase 0 Verification"
    echo "=========================================="
    
    ERRORS=0
    
    # Check dangerous files removed
    if [ -f "info.php" ]; then echo "❌ info.php still exists!"; ERRORS=$((ERRORS+1)); else echo "✅ info.php removed"; fi
    if [ -f "localhost.sql.txt" ]; then echo "❌ localhost.sql.txt still exists!"; ERRORS=$((ERRORS+1)); else echo "✅ localhost.sql.txt removed"; fi
    
    # Check dead files
    DEAD=$(find . -name "* copy*" -o -name "*Copy of*" | grep -v ".git" | wc -l)
    if [ "$DEAD" -gt 0 ]; then echo "❌ $DEAD copy files remaining"; ERRORS=$((ERRORS+1)); else echo "✅ No copy files"; fi
    
    # Check debug files
    if [ -f "ghom/debug_test.php" ]; then echo "❌ debug_test.php still exists!"; ERRORS=$((ERRORS+1)); else echo "✅ debug files removed"; fi
    
    # Check .gitignore
    if [ -f ".gitignore" ]; then echo "✅ .gitignore exists"; else echo "❌ .gitignore missing!"; ERRORS=$((ERRORS+1)); fi
    
    # Check .env.example
    if [ -f ".env.example" ]; then echo "✅ .env.example exists"; else echo "❌ .env.example missing!"; ERRORS=$((ERRORS+1)); fi
    
    # Check docs
    if [ -f "docs/ARCHITECTURE.md" ]; then echo "✅ ARCHITECTURE.md exists"; else echo "❌ ARCHITECTURE.md missing!"; ERRORS=$((ERRORS+1)); fi
    if [ -f "docs/TECH_DEBT.md" ]; then echo "✅ TECH_DEBT.md exists"; else echo "❌ TECH_DEBT.md missing!"; ERRORS=$((ERRORS+1)); fi
    if [ -f "docs/SETUP.md" ]; then echo "✅ SETUP.md exists"; else echo "❌ SETUP.md missing!"; ERRORS=$((ERRORS+1)); fi
    
    # Check no hardcoded tokens
    TOKEN_COUNT=$(grep -rn "7971076421:AAEp" --include="*.php" | wc -l)
    if [ "$TOKEN_COUNT" -gt 0 ]; then echo "❌ Telegram token still hardcoded in $TOKEN_COUNT files!"; ERRORS=$((ERRORS+1)); else echo "✅ No hardcoded tokens"; fi
    
    echo ""
    if [ "$ERRORS" -eq 0 ]; then
        echo "🎉 Phase 0 PASSED — All checks OK!"
    else
        echo "⚠️  Phase 0 has $ERRORS issue(s) to fix"
    fi
}

# ----------------------------------------------------------
# Main execution
# ----------------------------------------------------------
echo "Ready to execute Phase 0."
echo "Run functions in order: step1 through step8, then verify."
echo ""
echo "Usage:"
echo "  source scripts/phase0_setup.sh"
echo "  step1_create_repo"
echo "  step2_extract_and_clean"
echo "  step3_remove_dangerous"
echo "  step4_remove_dead_files"
echo "  step5_remove_debug"
echo "  step6_create_config"
echo "  step7_create_dirs"
echo "  step8_git_commit"
echo "  verify_phase0"
