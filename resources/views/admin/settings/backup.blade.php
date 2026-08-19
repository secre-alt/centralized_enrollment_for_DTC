@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Backup & Restore')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:var(--dtc-text);">Backup & Restore</h4>
            <p class="mb-0" style="color:var(--dtc-text-secondary); font-size:13px;">
                Manage your database backups and restore points
            </p>
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

    {{-- ── Create Backup ───────────────────────────────────────── --}}
    <div class="col-lg-4 mb-3">

        <div class="card mb-3">
            <div class="card-body" style="text-align:center; padding:32px 24px;">
                <div style="width:72px; height:72px; border-radius:20px;
                            background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                            display:flex; align-items:center; justify-content:center;
                            margin:0 auto 16px; font-size:28px; color:#FFC72C;">
                    <i class="fas fa-database"></i>
                </div>
                <h5 style="font-weight:700; color:var(--dtc-text); margin-bottom:8px;">
                    Create Backup
                </h5>
                <p style="font-size:13px; color:var(--dtc-text-secondary); line-height:1.6; margin-bottom:20px;">
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
            <div class="card-header font-weight-bold" style="color:var(--dtc-text);">
                <i class="fas fa-upload mr-2" style="color:#F59E0B;"></i>
                Restore from Backup
            </div>
            <div class="card-body">
                <div style="background:#FEF9C3; border:1.5px solid #FDE68A; border-radius:12px;
                            padding:14px 16px; margin-bottom:16px; font-size:12px; color:#92400E;">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Warning:</strong> Restoring will overwrite all current data.
                    Make sure you have a recent backup before proceeding.
                </div>

                <form method="POST" action="{{ route('admin.settings.backup.restore') }}"
                      enctype="multipart/form-data">
                    @csrf

                    <div class="form-group">
                        <label style="font-size:13px; font-weight:600; color:#374151;">
                            Upload .sql File
                        </label>
                        <div style="border:2px dashed var(--dtc-border); border-radius:12px;
                                    padding:24px; text-align:center; cursor:pointer;
                                    transition:border-color 0.2s; background:var(--dtc-surface-soft);"
                             id="drop-zone"
                             onclick="document.getElementById('sql-file').click()">
                            <i class="fas fa-file-code" style="font-size:28px; color:var(--dtc-text-muted); display:block; margin-bottom:8px;"></i>
                            <div style="font-size:13px; font-weight:600; color:var(--dtc-text); margin-bottom:4px;">
                                Click to select file
                            </div>
                            <div style="font-size:12px; color:var(--dtc-text-muted);" id="file-name">
                                .sql files only, max 50MB
                            </div>
                        </div>
                        <input type="file" name="sql_file" id="sql-file"
                               accept=".sql,.txt" style="display:none;"
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
    <div class="col-lg-8 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold" style="color:var(--dtc-text);">
                    <i class="fas fa-history mr-2" style="color:#0F4CDB;"></i>
                    Backup History
                </span>
                <span style="font-size:13px; color:var(--dtc-text-secondary);">
                    {{ $backups->count() }} backup{{ $backups->count() !== 1 ? 's' : '' }}
                </span>
            </div>
            <div class="card-body p-0">
                @forelse ($backups as $backup)
                <div style="padding:16px 20px; border-bottom:1px solid #F1F5F9;
                            display:flex; align-items:center; gap:16px;">

                    {{-- Icon --}}
                    <div style="width:44px; height:44px; border-radius:12px; flex-shrink:0;
                                background:{{ $backup->status === 'completed' ? '#DCFCE7' : '#FEE2E2' }};
                                display:flex; align-items:center; justify-content:center;
                                font-size:18px;
                                color:{{ $backup->status === 'completed' ? '#15803D' : '#DC2626' }};">
                        <i class="fas {{ $backup->status === 'completed' ? 'fa-check-circle' : 'fa-times-circle' }}"></i>
                    </div>

                    {{-- Info --}}
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:13px; font-weight:600; color:var(--dtc-text);
                                    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            {{ $backup->filename }}
                        </div>
                        <div style="font-size:12px; color:var(--dtc-text-secondary); margin-top:3px;">
                            {{ $backup->formatted_size }} •
                            {{ $backup->created_at->format('M d, Y h:i A') }} •
                            by {{ $backup->creator->name ?? 'System' }}
                        </div>
                    </div>

                    {{-- Status --}}
                    <span style="font-size:11px; font-weight:700; padding:3px 10px;
                                 border-radius:20px; flex-shrink:0;
                                 background:{{ $backup->status === 'completed' ? '#DCFCE7' : '#FEE2E2' }};
                                 color:{{ $backup->status === 'completed' ? '#15803D' : '#DC2626' }};">
                        {{ ucfirst($backup->status) }}
                    </span>

                    {{-- Actions --}}
                    <div style="display:flex; gap:6px; flex-shrink:0;">
                        @if($backup->status === 'completed')
                        <a href="{{ route('admin.settings.backup.download', $backup) }}"
                           style="background:#EEF2FF; color:#0F4CDB; border:none;
                                  padding:6px 12px; border-radius:8px; font-size:11px;
                                  font-weight:600; text-decoration:none; display:inline-flex;
                                  align-items:center; gap:4px;">
                            <i class="fas fa-download"></i> Download
                        </a>
                        @endif

                        <form method="POST"
                              action="{{ route('admin.settings.backup.delete', $backup) }}">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    style="background:#FEE2E2; color:#DC2626; border:none;
                                           padding:6px 10px; border-radius:8px; font-size:11px;
                                           font-weight:600; cursor:pointer;"
                                    onclick="return confirm('Remove this backup record?')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                @empty
                <div style="padding:48px 20px; text-align:center;">
                    <i class="fas fa-database" style="font-size:40px; color:var(--dtc-border); display:block; margin-bottom:14px;"></i>
                    <div style="font-size:14px; font-weight:600; color:var(--dtc-text); margin-bottom:6px;">
                        No backups yet
                    </div>
                    <div style="font-size:13px; color:var(--dtc-text-muted);">
                        Click "Backup Now" to create your first database backup.
                    </div>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Info card --}}
        <div class="card card-gradient mt-3"
             style="background:linear-gradient(135deg,#0F4CDB,#1a5feb); border:none;">
            <div class="card-body p-4">
                <h6 style="font-weight:700; margin-bottom:12px;">
                    <i class="fas fa-info-circle mr-2" style="color:#FFC72C;"></i>
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