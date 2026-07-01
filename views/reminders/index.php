<?php
$channelIcon = [
    'sms'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 10.81a19.79 19.79 0 01-3.07-8.63A2 2 0 012 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 14.92z"/></svg>',
    'email'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
    'whatsapp'  => '<svg viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;vertical-align:-2px;color:#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>',
];
$statusBadge = [
    'sent'      => 'badge-success',
    'scheduled' => 'badge-warning',
    'queued'    => 'badge-muted',
    'failed'    => 'badge-danger',
];
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?> mb-16">
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<!-- Root container carries every server value the external script needs.
     SECURITY FIX: previously this page had 21 inline onclick/onchange
     handlers plus a <script> body with PHP-interpolated values mixed into
     JS logic — both are blocked by the CSP now (script-src has no
     'unsafe-inline'). All behaviour moved to /assets/js/reminders.js,
     wired entirely via addEventListener + these data-* attributes. -->
<div id="remindersRoot"
     data-csrf="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>"
     data-i18n-resend-btn="<?= htmlspecialchars(__('reminders.resend_btn')) ?>"
     data-i18n-wa-no-api="<?= htmlspecialchars(__('reminders.wa_no_api')) ?>"
     data-i18n-open-wa="<?= htmlspecialchars(__('reminders.open_wa')) ?>"
     data-i18n-channel="<?= htmlspecialchars(__('reminders.channel')) ?>">

<!-- Tabs -->
<div class="d-flex gap-0 mb-24" style="border-bottom:1px solid var(--border)">
  <button type="button" class="tab-btn active" data-tab="compose" data-action="switch-tab">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
    <?= __('reminders.compose') ?>
  </button>
  <button type="button" class="tab-btn" data-tab="history" data-action="switch-tab">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
    <?= __('reminders.history') ?> <span class="badge badge-muted" style="margin-left:4px"><?= count($history) ?></span>
  </button>
</div>

<!-- ─── COMPOSE TAB ──────────────────────────────────────────────────────── -->
<div id="tab-compose">

  <!-- Channel selector -->
  <div class="card mb-20">
    <div class="d-flex gap-12 align-center flex-wrap">
      <span class="text-sm fw-600" style="min-width:70px"><?= __('reminders.channel') ?></span>
      <?php foreach (['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'Email'] as $ch => $label): ?>
      <label class="channel-pill <?= $ch === 'whatsapp' ? 'active' : '' ?>" id="pill-<?= $ch ?>">
        <input type="radio" name="channel" value="<?= $ch ?>" style="display:none" <?= $ch === 'whatsapp' ? 'checked' : '' ?>>
        <?= $channelIcon[$ch] ?> <?= $label ?>
      </label>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="d-flex gap-16 align-start" style="flex-wrap:wrap">

    <!-- Left: Recipients + Message -->
    <div style="flex:1;min-width:320px">

      <!-- Recipients -->
      <div class="card mb-16">
        <div class="d-flex justify-between align-center mb-12">
          <span class="fw-600 text-sm"><?= __('reminders.recipients') ?></span>
          <div class="d-flex gap-8">
            <button type="button" class="btn btn-ghost btn-sm" data-action="recipient-type" data-type="all"><?= __('reminders.all_active') ?></button>
            <button type="button" class="btn btn-ghost btn-sm" data-action="recipient-type" data-type="overdue"><?= __('reminders.overdue_only') ?></button>
            <button type="button" class="btn btn-ghost btn-sm" data-action="clear-recipients"><?= __('reminders.clear') ?></button>
          </div>
        </div>
        <input type="text" id="tenantSearch" class="form-control mb-10" placeholder="Search tenant or room…">
        <div id="tenantList" style="max-height:280px;overflow-y:auto;display:flex;flex-direction:column;gap:4px">
          <?php foreach ($tenants as $t): ?>
          <label class="tenant-row" data-name="<?= htmlspecialchars(strtolower($t['full_name'])) ?>"
                 data-room="<?= htmlspecialchars(strtolower($t['room_number'])) ?>"
                 data-balance="<?= (float)$t['balance'] ?>">
            <input type="checkbox" class="tenant-chk" value="<?= htmlspecialchars($t['id']) ?>"
                   data-phone="<?= htmlspecialchars($t['phone']) ?>"
                   data-email="<?= htmlspecialchars($t['email'] ?? '') ?>"
                   data-name="<?= htmlspecialchars($t['full_name']) ?>">
            <div style="flex:1">
              <span class="fw-600 text-sm"><?= htmlspecialchars($t['full_name']) ?></span>
              <span class="text-hint text-sm"> · Room <?= htmlspecialchars($t['room_number']) ?></span>
            </div>
            <?php if ((float)$t['balance'] > 0): ?>
            <span class="badge badge-danger text-sm">₹<?= number_format((float)$t['balance']) ?></span>
            <?php endif; ?>
          </label>
          <?php endforeach; ?>
        </div>
        <div class="mt-10 text-sm text-muted"><span id="selCount">0</span> selected</div>
      </div>

      <!-- Email subject (shown only for email channel) -->
      <div id="subjectRow" class="card mb-16" style="display:none">
        <label class="fw-600 text-sm d-block mb-8">Subject</label>
        <input type="text" id="subjectField" class="form-control" value="Rent Payment Reminder">
      </div>

      <!-- Message -->
      <div class="card mb-16">
        <div class="d-flex justify-between align-center mb-8">
          <span class="fw-600 text-sm"><?= __('reminders.message') ?></span>
          <button type="button" class="btn btn-ghost btn-sm" data-action="load-template"><?= __('reminders.load_template') ?></button>
        </div>
        <textarea id="msgBody" class="form-control" rows="8" placeholder="Type your message…"></textarea>
        <div class="d-flex justify-between mt-6">
          <span class="text-hint text-sm" id="charCount">0 chars</span>
          <span class="text-hint text-sm" id="smsSegments"></span>
        </div>
      </div>

      <!-- Attachment -->
      <div class="card mb-16">
        <div class="d-flex justify-between align-center mb-8">
          <span class="fw-600 text-sm">Attachment <span class="text-hint">(<?= __('reminders.optional_attachment') ?>)</span></span>
          <button type="button" class="btn btn-ghost btn-sm" id="clearFileBtn" data-action="clear-file" style="display:none"><?= __('reminders.remove') ?></button>
        </div>
        <input type="file" id="attachFile" accept=".pdf,.jpg,.jpeg,.png" class="form-control">
        <div id="fileInfo" class="text-sm text-muted mt-6" style="display:none"></div>
      </div>

    </div>

    <!-- Right: Schedule + Send -->
    <div style="width:260px;min-width:220px;flex-shrink:0">
      <div class="card mb-16">
        <span class="fw-600 text-sm d-block mb-12"><?= __('reminders.send_options') ?></span>

        <label class="text-sm fw-600 d-block mb-4"><?= __('reminders.schedule_optional') ?></label>
        <input type="datetime-local" id="scheduleAt" class="form-control mb-16"
               min="<?= date('Y-m-d\TH:i') ?>">

        <button type="button" class="btn btn-primary" id="sendBtn" style="width:100%;margin-bottom:8px" data-action="send">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          <span id="sendBtnLabel">Send Now</span>
        </button>

        <button type="button" class="btn btn-secondary" style="width:100%" data-action="preview">
          Preview message
        </button>
      </div>

      <div class="card" id="summaryCard" style="display:none">
        <span class="fw-600 text-sm d-block mb-8"><?= __('reminders.summary') ?></span>
        <div id="summaryBody" class="text-sm" style="line-height:1.8"></div>
      </div>
    </div>

  </div>
</div>

<!-- ─── HISTORY TAB ──────────────────────────────────────────────────────── -->
<div id="tab-history" style="display:none">
  <?php if ($history): ?>
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Date</th><th><?= __('reminders.channel') ?></th><th><?= __('reminders.recipients') ?></th>
            <th>Status</th><th>Sent/Failed</th><th>Scheduled</th><th style="width:120px"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $h):
            $recs = json_decode($h['recipients'], true) ?? [];
          ?>
          <tr>
            <td class="text-sm"><?= date('d M Y H:i', strtotime($h['created_at'])) ?></td>
            <td><?= ($channelIcon[$h['channel']] ?? '') . ' ' . ucfirst($h['channel']) ?></td>
            <td class="text-sm">
              <?php if (count($recs) <= 2): ?>
                <?= htmlspecialchars(implode(', ', array_column($recs, 'name'))) ?>
              <?php else: ?>
                <?= htmlspecialchars($recs[0]['name']) ?> +<?= count($recs) - 1 ?> more
              <?php endif; ?>
            </td>
            <td><span class="badge <?= $statusBadge[$h['status']] ?? 'badge-muted' ?>"><?= ucfirst($h['status']) ?></span></td>
            <td class="text-sm">
              <?php if ($h['sent_count'] || $h['fail_count']): ?>
                <span class="text-success"><?= (int)$h['sent_count'] ?> sent</span>
                <?php if ($h['fail_count']): ?> · <span class="text-danger"><?= (int)$h['fail_count'] ?> failed</span><?php endif; ?>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="text-sm text-muted">
              <?= $h['scheduled_at'] ? date('d M H:i', strtotime($h['scheduled_at'])) : '—' ?>
            </td>
            <td>
              <div class="d-flex gap-6">
                <button type="button" class="btn btn-ghost btn-sm" data-action="view-log" data-id="<?= htmlspecialchars($h['id']) ?>">View</button>
                <button type="button" class="btn btn-secondary btn-sm" data-action="open-resend"
                        data-id="<?= htmlspecialchars($h['id']) ?>"
                        data-message="<?= htmlspecialchars($h['message']) ?>"
                        data-subject="<?= htmlspecialchars($h['subject'] ?? '') ?>"
                        data-attachment="<?= htmlspecialchars($h['attachment_path'] ?? '') ?>"><?= __('reminders.resend') ?></button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php else: ?>
  <div class="card">
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:48px;height:48px"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
      <p><?= __('reminders.no_reminders') ?></p>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ─── Preview Modal ────────────────────────────────────────────────────── -->
<div id="previewModal" class="modal-backdrop" style="display:none">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <span class="fw-600"><?= __('reminders.message_preview') ?></span>
      <button type="button" class="btn btn-ghost btn-sm" data-action="close-modal" data-modal="previewModal">✕</button>
    </div>
    <div id="previewBody" class="modal-body" style="max-height:60vh;overflow-y:auto"></div>
  </div>
</div>

<!-- ─── Log Detail Modal ─────────────────────────────────────────────────── -->
<div id="logModal" class="modal-backdrop" style="display:none">
  <div class="modal" style="max-width:640px">
    <div class="modal-header">
      <span class="fw-600"><?= __('reminders.log_detail') ?></span>
      <button type="button" class="btn btn-ghost btn-sm" data-action="close-modal" data-modal="logModal">✕</button>
    </div>
    <div id="logBody" class="modal-body" style="max-height:70vh;overflow-y:auto"></div>
  </div>
</div>

<!-- ─── Resend Modal ──────────────────────────────────────────────────────── -->
<div id="resendModal" class="modal-backdrop" style="display:none">
  <div class="modal" style="max-width:540px">
    <div class="modal-header">
      <span class="fw-600"><?= __('reminders.resend_title') ?></span>
      <button type="button" class="btn btn-ghost btn-sm" data-action="close-modal" data-modal="resendModal">✕</button>
    </div>
    <div class="modal-body">
      <label class="fw-600 text-sm d-block mb-4">Subject (email only)</label>
      <input type="text" id="resendSubject" class="form-control mb-12">
      <label class="fw-600 text-sm d-block mb-4"><?= __('reminders.message') ?></label>
      <textarea id="resendMsg" class="form-control mb-12" rows="7"></textarea>
      <label class="fw-600 text-sm d-block mb-4">Attachment (replaces original if uploaded)</label>
      <input type="file" id="resendFile" class="form-control mb-4" accept=".pdf,.jpg,.jpeg,.png">
      <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:16px">
        <input type="checkbox" id="removeAttachment"> <?= __('reminders.remove_attachment') ?>
      </label>
      <input type="hidden" id="resendLogId">
      <button type="button" class="btn btn-primary" id="resendBtn" style="width:100%" data-action="resend">Resend Now</button>
    </div>
  </div>
</div>

<!-- ─── WhatsApp Links Modal ──────────────────────────────────────────────── -->
<div id="waModal" class="modal-backdrop" style="display:none">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <span class="fw-600"><?= __('reminders.wa_links_title') ?></span>
      <button type="button" class="btn btn-ghost btn-sm" data-action="close-modal" data-modal="waModal">✕</button>
    </div>
    <div id="waBody" class="modal-body" style="max-height:60vh;overflow-y:auto"></div>
  </div>
</div>

<!-- ─── Result Toast ─────────────────────────────────────────────────────── -->
<div id="toast" style="display:none;position:fixed;bottom:24px;right:24px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:14px 20px;box-shadow:var(--shadow);z-index:500;max-width:360px;font-size:14px"></div>

</div><!-- /#remindersRoot -->

<style>
.tab-btn{background:none;border:none;padding:10px 20px;font-size:14px;font-weight:600;cursor:pointer;border-bottom:2px solid transparent;color:var(--text-muted)}
.tab-btn.active{color:var(--primary);border-bottom-color:var(--primary)}
.channel-pill{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:20px;border:1.5px solid var(--border);cursor:pointer;font-size:13px;font-weight:600;transition:.15s;user-select:none}
.channel-pill.active{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary)}
.tenant-row{display:flex;align-items:center;gap:10px;padding:7px 10px;border-radius:var(--radius);cursor:pointer;font-size:13px;transition:.1s}
.tenant-row:hover{background:var(--surface-2)}
.tenant-row.selected{background:color-mix(in srgb,var(--primary) 8%,transparent)}
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:300;overflow-y:auto;padding:40px 16px;display:flex;align-items:flex-start;justify-content:center}
.modal{background:var(--surface);border-radius:var(--radius-xl);width:100%;box-shadow:var(--shadow);overflow:hidden}
.modal-header{display:flex;justify-content:space-between;align-items:center;padding:18px 24px;border-bottom:1px solid var(--border);font-size:15px}
.modal-body{padding:20px 24px}
.wa-link-btn{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-radius:var(--radius);background:var(--surface-2);margin-bottom:8px;font-size:13px}
</style>

<script src="<?= asset("/assets/js/reminders.js") ?>"></script>
