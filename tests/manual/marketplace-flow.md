# Marketplace Flow - Manual Testing Procedures

## Test 1: Browse Marketplace

**Objective**: Verify marketplace browsing and filtering

**Steps**:
1. Navigate to **Agentic → Marketplace**
2. Browse the default "Featured" tab
3. Switch to "Popular" tab
4. Switch to "Free" tab
5. Use search input to find "seo"
6. Use category dropdown filter

**Expected Results**:
- Agent cards load with name, icon, description, rating
- Tab switching updates the grid
- Search filters results in real-time or on submit
- Category filter updates results
- Loading spinner shows during fetch
- "No results" message for empty searches

**Pass/Fail**: _______
**Notes**: _______________________________

---

## Test 2: Agent Details Modal

**Objective**: Verify agent detail view works

**Steps**:
1. Click "View Details" on any agent card
2. Review the detail modal
3. Close modal via X button
4. Open another agent's details
5. Close modal via overlay click

**Expected Results**:
- Modal opens with full agent info (description, screenshots, changelog)
- Install/Activate button visible and functional
- Rating stars display correctly
- Version, author, last updated shown
- Modal closes via X and overlay click
- Body scroll locked while modal open

**Pass/Fail**: _______
**Notes**: _______________________________

---

## Test 3: Install Free Agent

**Objective**: Verify one-click install workflow for free agents

**Steps**:
1. Find a free agent in marketplace
2. Click "Install"
3. Wait for installation to complete
4. Verify agent appears in Installed Agents list

**Expected Results**:
- Install button changes to "Installing..." with spinner
- Success message on completion
- Button changes to "Activate"
- Agent directory created in `wp-content/agents/`
- `agent.php` file present in agent directory

**Pass/Fail**: _______
**Notes**: _______________________________

---

## Test 4: Install Premium Agent

**Objective**: Verify license-gated install workflow

**Steps**:
1. Find a premium agent in marketplace
2. Click "Install"
3. Enter an invalid license key
4. Observe error
5. Enter a valid license key
6. Complete installation

**Expected Results**:
- License key prompt appears for premium agents
- Invalid key shows clear error (expired, invalid, limit reached)
- Valid key proceeds with download
- License stored in `agentic_licenses` option
- Agent installs successfully

**Pass/Fail**: _______
**Notes**: _______________________________

---

## Test 5: Activate / Deactivate Installed Agent

**Objective**: Verify activation toggle from marketplace view

**Steps**:
1. Find an installed (but inactive) agent
2. Click "Activate"
3. Verify it becomes active
4. Click "Deactivate"
5. Verify it becomes inactive

**Expected Results**:
- Activation AJAX completes without page reload
- Button text updates immediately
- Agent appears/disappears from chat agent selector
- Active agents option updated in DB

**Pass/Fail**: _______
**Notes**: _______________________________

---

## Test 6: Agent Updates

**Objective**: Verify update detection and installation

**Steps**:
1. Install an agent
2. Manually lower its version in `agent.php` header
3. Wait for update check (or trigger manually)
4. Observe update notification
5. Click "Update"

**Expected Results**:
- Update available badge/notification appears
- "Update" button replaces "Activate/Deactivate"
- Update downloads and installs new version
- Settings/config preserved after update
- Version number updates in UI

**Pass/Fail**: _______
**Notes**: _______________________________

---

## Test 7: Rate an Agent

**Objective**: Verify agent rating submission

**Steps**:
1. Open details for an installed agent
2. Click a star rating (1-5)
3. Submit rating

**Expected Results**:
- Star selection highlights correctly
- Rating submits via AJAX
- Success confirmation shown
- Average rating updates
- Can only rate once per site

**Pass/Fail**: _______
**Notes**: _______________________________

---

## Test 8: Developer Revenue Page

**Objective**: Verify developer revenue dashboard

**Steps**:
1. Navigate to **Agentic → Revenue**
2. Enter a developer API key
3. View revenue statistics
4. Test "Disconnect" button

**Expected Results**:
- API key form validates on save
- Revenue charts render with Chart.js
- Stats show downloads, installs, earnings
- Disconnect removes key and clears data
- Invalid key shows clear error

**Pass/Fail**: _______
**Notes**: _______________________________

---

## Test 9: Offline / API Unavailable

**Objective**: Verify graceful handling when marketplace API is down

**Steps**:
1. Disconnect internet (or block `agentic-plugin.com` in hosts file)
2. Navigate to Marketplace page
3. Try to install an agent
4. Restore connection

**Expected Results**:
- Timeout error shows user-friendly message
- "Retry" option available
- Previously cached agents may still display
- No fatal errors or blank pages
- Reconnection restores functionality

**Pass/Fail**: _______
**Notes**: _______________________________
