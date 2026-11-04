import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';

const EXTENSION_ID = 'tacraft-sociality-auto-duea';
const PREFIX = `${EXTENSION_ID}.admin`;

function flatten(node: any): string {
  if (node === null || node === undefined || node === false) {
    return '';
  }

  if (typeof node === 'string' || typeof node === 'number') {
    return String(node);
  }

  if (Array.isArray(node)) {
    return node.map(flatten).join('');
  }

  if (typeof node === 'object' && node.children !== undefined) {
    return flatten(node.children);
  }

  return '';
}

interface Status {
  pending: number;
  eligible: number;
  deletedTotal: number;
  lastDeleted: number;
  lastRun: string | null;
  enabled: boolean;
  intervalHours: number;
  graceDays: number;
  batch: number;
}

function text(key: string, params: Record<string, unknown> = {}): string {
  try {
    const translated = flatten(app.translator.trans(key, params) as any);

    return translated || key;
  } catch (e) {
    return key;
  }
}

function apiUrl(): string {
  return app.forum.attribute<string>('apiUrl');
}

function redraw(): void {
  if (typeof m !== 'undefined') m.redraw();
}

class AutoDueaPanel extends Component {
  status: Status | null = null;
  loading = true;
  running = false;
  message: string | null = null;
  error: string | null = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.load();
  }

  load(): void {
    this.loading = true;

    app
      .request({ method: 'GET', url: `${apiUrl()}/auto-duea/status` })
      .then((data: any) => {
        this.status = data as Status;
      })
      .catch((error: any) => {
        this.error = text(`${PREFIX}.run_failed`, { error: this.errorText(error) });
      })
      .finally(() => {
        this.loading = false;
        redraw();
      });
  }

  errorText(error: any): string {
    const detail = error?.response?.errors?.[0]?.detail || error?.detail || error?.message;

    return detail ? String(detail) : 'Unknown error';
  }

  run(): void {
    if (this.running) {
      return;
    }

    if (!confirm(text(`${PREFIX}.confirm_run`))) {
      return;
    }

    this.running = true;
    this.message = null;
    this.error = null;

    app
      .request({ method: 'POST', url: `${apiUrl()}/auto-duea/run`, body: { all: true } })
      .then((data: any) => {
        this.message = text(`${PREFIX}.run_result`, { count: data.deleted });

        if (data.status) {
          this.status = data.status as Status;
        }
      })
      .catch((error: any) => {
        this.error = text(`${PREFIX}.run_failed`, { error: this.errorText(error) });
      })
      .finally(() => {
        this.running = false;
        redraw();
      });
  }

  stat(labelKey: string, value: number | string, background: string) {
    return (
      <div
        className="AutoDueaPanel-stat"
        style={{
          flex: '1 1 150px',
          minWidth: '150px',
          background,
          borderRadius: 'var(--border-radius)',
          padding: '12px 15px',
        }}
      >
        <div style={{ fontSize: '22px', fontWeight: 600, lineHeight: 1.3 }}>{value}</div>
        <div className="helpText" style={{ marginTop: '2px' }}>
          {text(labelKey)}
        </div>
      </div>
    );
  }

  view() {
    const status = this.status;

    return (
      <div className="AutoDueaPanel" style={{ marginBottom: '20px' }}>
        <p className="helpText">{text(`${PREFIX}.intro`)}</p>

        <h4 style={{ marginTop: '15px' }}>{text(`${PREFIX}.stats_title`)}</h4>

        {status ? (
          <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap', marginBottom: '15px' }}>
            {this.stat(`${PREFIX}.pending`, status.pending, 'var(--control-bg)')}
            {this.stat(`${PREFIX}.eligible`, status.eligible, 'var(--control-bg)')}
            {this.stat(`${PREFIX}.deleted_total`, status.deletedTotal, 'var(--control-bg)')}
            {this.stat(
              `${PREFIX}.last_run`,
              status.lastRun ? new Date(status.lastRun).toLocaleString() : text(`${PREFIX}.never`),
              'var(--control-bg)'
            )}
          </div>
        ) : (
          <p className="helpText">{this.loading ? '…' : ''}</p>
        )}

        {this.message ? <p className="helpText">{this.message}</p> : null}
        {this.error ? (
          <p className="helpText" style={{ color: 'var(--error-color, #d93025)' }}>
            {this.error}
          </p>
        ) : null}

        <Button
          className="Button Button--primary"
          icon="fas fa-user-slash"
          loading={this.running}
          disabled={this.running || !status || status.eligible === 0}
          onclick={() => this.run()}
        >
          {text(`${PREFIX}.run_button`)}
        </Button>
      </div>
    );
  }
}

app.initializers.add(EXTENSION_ID, () => {
  app.registry
    .for(EXTENSION_ID)
    .registerSetting({
      type: 'bool',
      setting: 'auto_duea_enabled',
      label: text(`${PREFIX}.enabled_label`),
      help: text(`${PREFIX}.enabled_help`),
    })
    .registerSetting({
      type: 'number',
      setting: 'auto_duea_interval_hours',
      label: text(`${PREFIX}.interval_label`),
      help: text(`${PREFIX}.interval_help`),
    })
    .registerSetting({
      type: 'number',
      setting: 'auto_duea_grace_days',
      label: text(`${PREFIX}.grace_label`),
      help: text(`${PREFIX}.grace_help`),
    })
    .registerSetting({
      type: 'number',
      setting: 'auto_duea_batch',
      label: text(`${PREFIX}.batch_label`),
      help: text(`${PREFIX}.batch_help`),
    });

  app.registry
    .for(EXTENSION_ID)
    .registerSetting(
      function (this: any) {
        return <AutoDueaPanel />;
      } as any,
      100,
      'auto-duea-panel'
    );
});
