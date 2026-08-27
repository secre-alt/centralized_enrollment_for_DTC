@extends('adminlte::page')
@include('partials.navbar')

@section('plugins.adminUsers', true)

@section('title', 'Backup & Restore')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">Backup &amp; Restore</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="u-text-secondary">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.settings.index') }}" class="u-text-secondary">Settings</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Backup &amp; Restore</li>
                </ol>
            </nav>
        </div>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success">
        <i class="fas fa-check-circle mr-2"></i> {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle mr-2"></i> {{ session('error') }}
    </div>
@endif

<div class="row">

    {{-- ── Create Backup & Restore ───────────────────────────────────────── --}}
    <div class="col-lg-4 mb-3 order-2 order-lg-1">

        <div class="card mb-3">
            <div class="card-body backup-action-card">
                <div class="backup-action-icon">
                    <i class="fas fa-database"></i>
                </div>
                <h5 class="backup-action-title">Create Backup</h5>
                <p class="backup-action-text">
                    Export your entire database as a <code>.sql</code> file.
                    This includes all tables, records, and data.
                </p>
                <form method="POST" action="{{ route('admin.settings.backup.create') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-block"
                            onclick="return confirm('Create a full database backup now?')">
                        <i class="fas fa-download mr-2"></i> Backup Now
                    </button>
                </form>
            </div>
        </div>

        {{-- ── Restore ─── --}}
        <div class="card">
            <div class="card-header font-weight-bold u-text">
                <i class="fas fa-upload mr-2" style="color:#F59E0B;"></i>
                Restore from Backup
            </div>
            <div class="card-body">
                <div class="backup-warning">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Warning:</strong> Restoring will overwrite all current data.
                    Make sure you have a recent backup before proceeding.
                </div>

                <form method="POST" action="{{ route('admin.settings.backup.restore') }}"
                      enctype="multipart/form-data">
                    @csrf

                    <div class="form-group">
                        <label class="u-gray-bold-sm">
                            Upload .sql File
                        </label>
                        <div class="backup-drop-zone" id="drop-zone"
                             onclick="document.getElementById('sql-file').click()">
                            <i class="fas fa-file-code backup-drop-icon"></i>
                            <div class="backup-drop-title">Click to select file</div>
                            <div class="backup-drop-sub" id="file-name">.sql files only, max 50MB</div>
                        </div>
                        <input type="file" name="sql_file" id="sql-file"
                               accept=".sql,.txt" class="u-hidden"
                               onchange="updateFileName(this)">
                    </div>

                    @if($errors->has('sql_file'))
                        <div style="color:#DC2626; font-size:12px; margin-bottom:12px;">
                            {{ $errors->first('sql_file') }}
                        </div>
                    @endif

                    <button type="submit" class="btn btn-warning btn-block"
                            onclick="return confirm('⚠️ This will OVERWRITE all current data. Are you absolutely sure?')">
                        <i class="fas fa-undo mr-2"></i> Restore Database
                    </button>
                </form>
            </div>
        </div>

    </div>

    {{-- ── Backup History ───────────────────────────────────────── --}}
    <div class="col-lg-8 mb-3 order-1 order-lg-2">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
                <span class="font-weight-bold u-text">
                    <i class="fas fa-history mr-2 u-link"></i>
                    Backup History
                </span>
                <span class="u-text-secondary-sm">
                    {{ $backups->count() }} backup{{ $backups->count() !== 1 ? 's' : '' }}
                </span>
            </div>
            <div class="card-body p-0">
                @forelse ($backups as $backup)
                <div class="backup-row">

                    <div class="backup-row-main">
                        {{-- Icon --}}
                        <div class="backup-row-icon" style="
                            background:{{ $backup->status === 'completed' ? '#DCFCE7' : '#FEE2E2' }};
                            color:{{ $backup->status === 'completed' ? '#15803D' : '#DC2626' }};">
                            <i class="fas {{ $backup->status === 'completed' ? 'fa-check-circle' : 'fa-times-circle' }}"></i>
                        </div>

                        {{-- Info --}}
                        <div class="backup-row-info">
                            <div class="backup-row-filename">
                                {{ $backup->filename }}
                            </div>
                            <div class="backup-row-meta">
                                {{ $backup->formatted_size }} &bull;
                                {{ $backup->created_at->format('M d, Y h:i A') }} &bull;
                                by {{ $backup->creator->name ?? 'System' }}
                            </div>
                        </div>
                    </div>

                    <div class="backup-row-side">
                        {{-- Status --}}
                        <span class="backup-status-pill" style="
                            background:{{ $backup->status === 'completed' ? '#DCFCE7' : '#FEE2E2' }};
                            color:{{ $backup->status === 'completed' ? '#15803D' : '#DC2626' }};">
                            {{ ucfirst($backup->status) }}
                        </span>

                        {{-- Actions --}}
                        <div class="backup-row-actions">
                            @if($backup->status === 'completed')
                            <a href="{{ route('admin.settings.backup.download', $backup) }}"
                               class="backup-btn backup-btn-download">
                                <i class="fas fa-download"></i> <span class="d-none d-sm-inline">Download</span>
                            </a>
                            @endif

                            <form method="POST"
                                  action="{{ route('admin.settings.backup.delete', $backup) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="backup-btn backup-btn-delete"
                                        onclick="return confirm('Remove this backup record?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <div class="backup-empty">
                    <i class="fas fa-database backup-empty-icon"></i>
                    <div class="backup-empty-title">No backups yet</div>
                    <div class="backup-empty-sub">Click "Backup Now" to create your first database backup.</div>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Info card --}}
        <div class="card card-gradient mt-3"
             style="background:linear-gradient(135deg,#0F4CDB,#1a5feb); border:none;">
            <div class="card-body p-4">
                <h6 style="font-weight:700; margin-bottom:12px;">
                    <i class="fas fa-info-circle mr-2 u-accent"></i>
                    Backup Tips
                </h6>
                <div style="font-size:13px; line-height:1.8; opacity:0.9;">
                    @foreach([
                        'Create backups before making major system changes.',
                        'Download and store backups in a secure external location.',
                        'Test restores periodically to make sure backups are valid.',
                        'Backups are stored in <code style="background:rgba(255,255,255,0.15); padding:1px 6px; border-radius:4px;">storage/app/backups/</code>',
                    ] as $tip)
                    <div style="display:flex; gap:10px; margin-bottom:8px;">
                        <i class="fas fa-check-circle" style="color:#FFC72C; margin-top:3px; flex-shrink:0;"></i>
                        <span>{!! $tip !!}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@push('css')
<style>
/* ── Backup & Restore ─────────────────────────────────────────── */
.backup-action-card {
    text-align: center;
    padding: 32px 24px;
}
.backup-action-icon {
    width: 72px; height: 72px; border-radius: 20px;
    background: linear-gradient(135deg,#0F4CDB,#1a5feb);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px; font-size: 28px; color: #FFC72C;
}
.backup-action-title { font-weight: 700; color: var(--dtc-text); margin-bottom: 8px; }
.backup-action-text  { font-size: 13px; color: var(--dtc-text-secondary); line-height: 1.6; margin-bottom: 20px; }

.backup-warning {
    background: #FEF9C3; border: 1.5px solid #FDE68A; border-radius: 12px;
    padding: 14px 16px; margin-bottom: 16px; font-size: 12px; color: #92400E;
}
body.dtc-dark .backup-warning {
    background: rgba(253, 230, 138, 0.12);
    border-color: rgba(253, 230, 138, 0.3);
    color: #FDE68A;
}

.backup-drop-zone {
    border: 2px dashed var(--dtc-border); border-radius: 12px;
    padding: 24px; text-align: center; cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
    background: var(--dtc-surface-soft);
}
.backup-drop-icon  { font-size: 28px; color: var(--dtc-text-muted); display: block; margin-bottom: 8px; }
.backup-drop-title { font-size: 13px; font-weight: 600; color: var(--dtc-text); margin-bottom: 4px; }
.backup-drop-sub   { font-size: 12px; color: var(--dtc-text-muted); word-break: break-word; }

.backup-empty { padding: 48px 20px; text-align: center; }
.backup-empty-icon  { font-size: 40px; color: var(--dtc-border); display: block; margin-bottom: 14px; }
.backup-empty-title { font-size: 14px; font-weight: 600; color: var(--dtc-text); margin-bottom: 6px; }
.backup-empty-sub   { font-size: 13px; color: var(--dtc-text-muted); }

/* Backup history row */
.backup-row {
    padding: 16px 20px;
    border-bottom: 1px solid var(--dtc-border-soft);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.backup-row:last-child { border-bottom: none; }

.backup-row-main {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    flex: 1 1 240px;
}
.backup-row-icon {
    width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
}
.backup-row-info { flex: 1; min-width: 0; }
.backup-row-filename {
    font-size: 13px; font-weight: 600; color: var(--dtc-text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.backup-row-meta {
    font-size: 12px; color: var(--dtc-text-secondary); margin-top: 3px;
    white-space: normal;
}

.backup-row-side {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    margin-left: auto;
}

.backup-status-pill {
    font-size: 11px; font-weight: 700; padding: 3px 10px;
    border-radius: 20px; flex-shrink: 0; white-space: nowrap;
}

.backup-row-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}
.backup-btn {
    border: none; padding: 6px 12px; border-radius: 8px;
    font-size: 11px; font-weight: 600; text-decoration: none;
    display: inline-flex; align-items: center; gap: 4px; cursor: pointer;
}
.backup-btn-download { background: #EEF2FF; color: #0F4CDB; }
.backup-btn-download:hover { background: #E0E7FF; color: #0F4CDB; text-decoration: none; }
.backup-btn-delete   { background: #FEE2E2; color: #DC2626; }
.backup-btn-delete:hover { background: #FECACA; }

/* ── Mobile ───────────────────────────────────────────────────── */
@media (max-width: 575.98px) {
    .backup-row {
        flex-direction: column;
        align-items: stretch;
    }
    .backup-row-side {
        justify-content: space-between;
        margin-left: 0;
        width: 100%;
    }
}
</style>
@endpush

@section('js')
<script>
// File drop zone
function updateFileName(input) {
    const label = document.getElementById('file-name');
    const zone  = document.getElementById('drop-zone');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const size = (file.size / 1024).toFixed(1) + ' KB';
        label.textContent = file.name + ' (' + size + ')';
        label.style.color = '#15803D';
        zone.style.borderColor = '#0F4CDB';
        zone.style.background  = '#EEF2FF';
    }
}

// Drag and drop
const zone = document.getElementById('drop-zone');
if (zone) {
    zone.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.style.borderColor = '#0F4CDB';
        this.style.background  = '#EEF2FF';
    });
    zone.addEventListener('dragleave', function() {
        this.style.borderColor = 'var(--dtc-border)';
        this.style.background  = 'var(--dtc-surface-soft)';
    });
    zone.addEventListener('drop', function(e) {
        e.preventDefault();
        const input = document.getElementById('sql-file');
        input.files = e.dataTransfer.files;
        updateFileName(input);
    });
}
</script>
@endsection
