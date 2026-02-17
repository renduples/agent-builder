# Manual Testing Procedures

This document contains manual test procedures for UI/UX features that cannot be easily automated.

## Frontend Chat Interface Testing

### Test 1: Basic Chat Interaction

**Objective**: Verify chat interface loads and responds correctly

**Steps**:
1. Insert `[agentic_chat]` shortcode on a test page
2. Navigate to the page as a logged-in user
3. Type a message: "Hello, can you help me?"
4. Click "Send" button

**Expected Results**:
- Chat interface loads without errors
- Send button is enabled when text is entered
- Message appears in chat history
- AI response appears within 5 seconds
- Response formatting is correct (no HTML/JS injection)

**Pass/Fail**: _______

**Notes**: _______________________________

---

### Test 2: Rate Limiting Display

**Objective**: Verify rate limit messages display correctly

**Steps**:
1. Send 10+ messages rapidly (within 1 minute)
2. Observe behavior after 10th message

**Expected Results**:
- After 10th message, rate limit warning appears
- Clear message explains wait time
- Send button is disabled
- Previously sent messages remain visible

**Pass/Fail**: _______

**Notes**: _______________________________

---

### Test 3: Error Handling

**Objective**: Verify error messages display gracefully

**Steps**:
1. Temporarily disable API key in settings
2. Send a message in chat
3. Observe error handling

**Expected Results**:
- User-friendly error message (no technical details)
- Chat interface remains functional
- Error is logged (check browser console)

**Pass/Fail**: _______

**Notes**: _______________________________

---

### Test 4: Mobile Responsiveness

**Objective**: Verify chat works on mobile devices

**Steps**:
1. Open chat interface on mobile device (or resize browser to 375px width)
2. Send several messages
3. Scroll through chat history

**Expected Results**:
- Interface adapts to mobile screen
- No horizontal scrolling required
- Buttons are touch-friendly (min 44px)
- Text is readable (min 16px font)

**Pass/Fail**: _______

**Notes**: _______________________________

---

## Admin Dashboard Testing

### Test 5: Dashboard Widgets

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

### Test 6: Agent Management UI

**Objective**: Verify agent activation/deactivation workflow

**Steps**:
1. Navigate to **Agentic → Agents**
2. Click "Activate" on a bundled agent
3. Verify activation
4. Click "Deactivate" on the same agent
5. Verify deactivation

**Expected Results**:
- Activation button changes to "Deactivate"
- Success message displays
- Page updates without full reload (AJAX)
- Active agent count updates
- No duplicate entries appear

**Pass/Fail**: _______

**Notes**: _______________________________

---

### Test 7: Settings Page UX

**Objective**: Verify settings save and validate correctly

**Steps**:
1. Navigate to **Agentic → Settings**
2. Change LLM provider to "Anthropic"
3. Enter invalid API key (e.g., "test123")
4. Click "Save Settings"
5. Enter valid API key
6. Click "Save Settings"

**Expected Results**:
- Invalid key shows clear error message
- Valid key saves successfully
- Success notification appears
- Settings persist after page reload
- No console errors

**Pass/Fail**: _______

**Notes**: _______________________________

---

### Test 8: Audit Log Viewer

**Objective**: Verify audit log displays and filters correctly

**Steps**:
1. Navigate to **Agentic → Audit Log**
2. Observe recent entries
3. Use date filter to show last 7 days
4. Use agent filter to show specific agent
5. Export log (if feature exists)

**Expected Results**:
- Log entries display in reverse chronological order
- Timestamps are in local timezone
- Filters work correctly
- Export generates valid file format
- No pagination errors

**Pass/Fail**: _______

**Notes**: _______________________________

---

### Test 10: Security Log Viewer

**Objective**: Verify security events display correctly

**Steps**:
1. Navigate to **Agentic → Security Log**
2. Review security events
3. Click on an event to view details
4. Use IP address filter

**Expected Results**:
- Events categorized correctly (blocked, rate_limited, pii_warning)
- Severity colors display (red/yellow/orange)
- Event details show full context
- IP filter works correctly
- Top patterns/IPs widgets accurate

**Pass/Fail**: _______

**Notes**: _______________________________

---

## Accessibility Testing

### Test 11: Keyboard Navigation

**Objective**: Verify all features accessible via keyboard

**Steps**:
1. Navigate entire admin interface using only Tab/Shift+Tab
2. Test all forms and buttons with Enter/Space
3. Verify focus indicators are visible

**Expected Results**:
- All interactive elements reachable via Tab
- Focus order is logical
- Focus indicators clearly visible
- No keyboard traps
- Skip navigation links present

**Pass/Fail**: _______

**Notes**: _______________________________

---

### Test 12: Screen Reader Compatibility

**Objective**: Verify screen reader announces content correctly

**Tools**: NVDA (Windows), VoiceOver (Mac), or JAWS

**Steps**:
1. Enable screen reader
2. Navigate through admin dashboard
3. Fill out settings form
4. Use chat interface

**Expected Results**:
- All content announced clearly
- Form labels associated correctly
- Button purposes clear
- Error messages announced
- ARIA labels present where needed

**Pass/Fail**: _______

**Notes**: _______________________________

---

## Performance Testing

### Test 13: Page Load Times

**Objective**: Verify acceptable page load performance

**Tools**: Browser DevTools Network tab

**Steps**:
1. Clear browser cache
2. Navigate to **Agentic → Dashboard**
3. Record page load time

**Expected Results**:
- Dashboard loads in < 2 seconds
- Total assets < 500KB per page
- No render-blocking resources
- Images optimized

**Pass/Fail**: _______

**Notes**: _______________________________

---

### Test 14: Large Dataset Handling

**Objective**: Verify UI handles large datasets gracefully

**Steps**:
1. Generate 100+ audit log entries (via automation script)
2. Navigate to Audit Log page
3. Test pagination and filtering
4. Observe browser performance

**Expected Results**:
- Page remains responsive
- Pagination works smoothly
- No UI freezing
- Memory usage acceptable (< 200MB)
- Console shows no errors

**Pass/Fail**: _______

**Notes**: _______________________________

---

## Cross-Browser Testing

### Test 15: Browser Compatibility

**Objective**: Verify plugin works across major browsers

**Browsers to Test**:
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

**Steps** (repeat for each browser):
1. Navigate to dashboard
2. Activate/deactivate an agent
3. Send chat message
4. Save settings

**Expected Results**:
- All features work identically
- No layout issues
- No JavaScript errors
- CSS renders correctly

**Chrome**: Pass/Fail _______
**Firefox**: Pass/Fail _______
**Safari**: Pass/Fail _______
**Edge**: Pass/Fail _______

---

## Security Testing

### Test 16: XSS Prevention

**Objective**: Verify XSS attempts are blocked

**Steps**:
1. Try entering `<script>alert('XSS')</script>` in chat
2. Try saving settings with XSS in agent name field
3. Observe handling

**Expected Results**:
- Scripts are escaped/removed
- No alert boxes appear
- Content displays as plain text
- Security log records attempt

**Pass/Fail**: _______

**Notes**: _______________________________

---

### Test 17: CSRF Protection

**Objective**: Verify nonce validation works

**Steps**:
1. Open browser DevTools → Network tab
2. Activate an agent
3. Copy the request
4. Modify the nonce value
5. Replay the request via curl/Postman

**Expected Results**:
- Modified request rejected
- Error message indicates invalid nonce
- Action not performed
- User redirected appropriately

**Pass/Fail**: _______

**Notes**: _______________________________

---

## Testing Sign-Off

**Tester Name**: _______________________________

**Date**: _______________________________

**Overall Pass/Fail**: _______

**Critical Issues Found**: _______________________________

**Recommendations**: _______________________________
