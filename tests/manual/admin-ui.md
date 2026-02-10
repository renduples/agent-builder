# Admin UI - Manual Testing Procedures

## Test 1: Dashboard Widgets

**Objective**: Verify all dashboard widgets display correctly

**Steps**:
1. Navigate to **Agentic → Dashboard**
2. Observe all widgets and statistics
3. Click on agent cards to view details

**Expected Results**:
- All widgets load without errors
- Statistics show accurate numbers
- Charts render correctly (Chart.js)
- Agent cards are clickable and responsive
- No JavaScript errors in console

**Pass/Fail**: _______
**Notes**: _______________________________

---

## Test 2: Agent Management

**Objective**: Verify agent activation/deactivation workflow

**Steps**:
1. Navigate to **Agentic → Agents**
2. Click "Activate" on a bundled agent
3. Verify activation succeeds
4. Click "Deactivate" on the same agent
5. Verify deactivation succeeds
6. Try activating a agent, then deleting it

**Expected Results**:
- Activation button changes to "Deactivate" on success
- Success message displays
- Page updates without full reload (AJAX)
- Active agent count updates in sidebar
- Delete requires deactivation first
- No duplicate entries appear

**Pass/Fail**: _______
**Notes**: _______________________________

---

## Test 3: Settings Page

**Objective**: Verify settings save and validate correctly

**Steps**:
1. Navigate to **Agentic → Settings**
2. Change LLM provider dropdown
3. Enter invalid API key (e.g., "test123")
4. Click "Test API Key" button
5. Enter valid API key
6. Click "Save Settings"
7. Reload page

**Expected Results**:
- Invalid key shows clear error via test button
- Valid key saves successfully with success notification
- Provider-specific fields show/hide appropriately
- Settings persist after page reload
- No console errors

**Pass/Fail**: _______
**Notes**: _______________________________

---

## Test 4: Audit Log Viewer

**Objective**: Verify audit log displays and filters correctly

**Steps**:
1. Navigate to **Agentic → Audit Log** (or Dashboard section)
2. Observe recent entries
3. Filter by agent
4. Filter by action type
5. Check pagination with many entries

**Expected Results**:
- Entries display in reverse chronological order
- Timestamps in local timezone
- Filters work correctly
- Pagination navigates without errors
- Token/cost columns show correct data

**Pass/Fail**: _______
**Notes**: _______________________________

---

## Test 5: Security Log Viewer

**Objective**: Verify security events display correctly

**Steps**:
1. Navigate to **Agentic → Security Log**
2. Review security events
3. Send a blocked message to generate an event
4. Refresh and verify event appears
5. Filter by event type

**Expected Results**:
- Events categorized correctly (blocked, rate_limited, pii_warning)
- Severity colors display appropriately
- Event details show message and pattern matched
- IP filter works correctly
- Stats charts accurate

**Pass/Fail**: _______
**Notes**: _______________________________

---

## Test 6: Approval Queue

**Objective**: Verify pending approval UI works

**Steps**:
1. Navigate to **Agentic → Approvals** (or dashboard)
2. If pending items exist, click "Approve" on one
3. Click "Reject" on another
4. Verify counts update

**Expected Results**:
- Pending items list with agent, action, reasoning
- Approve button marks as approved (shows who approved)
- Reject button marks as rejected
- Counts in admin bar/dashboard update
- Expired items shown differently

**Pass/Fail**: _______
**Notes**: _______________________________

---

## Test 7: System Check Page

**Objective**: Verify system requirements checker works

**Steps**:
1. Navigate to **Agentic → Settings → System Check** (or run via REST)
2. Click "Run System Check"
3. Review all check results

**Expected Results**:
- All 7 checks display (PHP, WP, memory, exec time, permalinks, API key, REST)
- Passing checks show green ✓
- Failing/warning checks show yellow/red indicator
- Overall status accurate
- Results cached in option for admin notice

**Pass/Fail**: _______
**Notes**: _______________________________

---

## Test 8: Keyboard Navigation

**Objective**: Verify all admin features accessible via keyboard

**Steps**:
1. Navigate admin pages using Tab/Shift+Tab only
2. Test all forms and buttons with Enter/Space
3. Verify focus indicators are visible

**Expected Results**:
- All interactive elements reachable via Tab
- Focus order is logical (top-to-bottom, left-to-right)
- Focus indicators clearly visible
- No keyboard traps
- Modal dialogs trap focus correctly

**Pass/Fail**: _______
**Notes**: _______________________________
