import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils';
import { TurnstileWidget } from '../../../src/components/auth/TurnstileWidget';

// Turnstile APIをモック
const mockTurnstileRender = vi.fn();
const mockTurnstileReset = vi.fn();
const mockTurnstileRemove = vi.fn();

window.turnstile = {
  render: mockTurnstileRender,
  reset: mockTurnstileReset,
  remove: mockTurnstileRemove,
};

describe('TurnstileWidget', () => {
  const mockOnVerify = vi.fn();
  const mockOnError = vi.fn();
  const testSiteKey = 'test-site-key';

  beforeEach(() => {
    vi.clearAllMocks();
    process.env.REACT_APP_TURNSTILE_SITE_KEY = testSiteKey;
    window.turnstile = {
      render: mockTurnstileRender,
      reset: mockTurnstileReset,
      remove: mockTurnstileRemove,
    };
  });

  it('Turnstileウィジェットが正しく初期化される', () => {
    mockTurnstileRender.mockReturnValue('widget-id');

    render(<TurnstileWidget siteKey={testSiteKey} onVerify={mockOnVerify} onError={mockOnError} />);

    expect(mockTurnstileRender).toHaveBeenCalledWith(
      expect.any(Element),
      expect.objectContaining({
        sitekey: expect.any(String),
        callback: expect.any(Function),
        'error-callback': expect.any(Function),
      })
    );
  });

  it('認証成功時にonVerifyコールバックが呼ばれる', () => {
    let successCallback: (token: string) => void;

    mockTurnstileRender.mockImplementation((element, options) => {
      successCallback = options.callback;
      return 'widget-id';
    });

    render(<TurnstileWidget siteKey={testSiteKey} onVerify={mockOnVerify} onError={mockOnError} />);

    const testToken = 'test-turnstile-token';
    successCallback!(testToken);

    expect(mockOnVerify).toHaveBeenCalledWith(testToken);
  });

  it('認証エラー時にonErrorコールバックが呼ばれる', () => {
    let errorCallback: () => void;

    mockTurnstileRender.mockImplementation((element, options) => {
      errorCallback = options['error-callback'];
      return 'widget-id';
    });

    render(<TurnstileWidget siteKey={testSiteKey} onVerify={mockOnVerify} onError={mockOnError} />);

    errorCallback!();

    expect(mockOnError).toHaveBeenCalled();
  });

  it('コンポーネントアンマウント時にウィジェットが削除される', () => {
    const widgetId = 'test-widget-id';
    mockTurnstileRender.mockReturnValue(widgetId);

    const { unmount } = render(
      <TurnstileWidget siteKey={testSiteKey} onVerify={mockOnVerify} onError={mockOnError} />
    );

    unmount();

    expect(mockTurnstileRemove).toHaveBeenCalledWith(widgetId);
  });

  it('Turnstile APIが利用できない場合の処理', () => {
    // turnstile APIを一時的に削除
    const originalTurnstile = window.turnstile;
    delete (window as any).turnstile;

    render(<TurnstileWidget siteKey={testSiteKey} onVerify={mockOnVerify} onError={mockOnError} />);

    // TurnstileWidgetのコンテナが存在することを確認
    const containers = screen.getAllByRole('generic');
    expect(containers.length).toBeGreaterThan(0);

    // 復元
    window.turnstile = originalTurnstile;
  });

  it('サイトキーが空の場合でもコンポーネントが正常に動作する', () => {
    render(<TurnstileWidget siteKey="" onVerify={mockOnVerify} onError={mockOnError} />);

    const containers = screen.getAllByRole('generic');
    expect(containers.length).toBeGreaterThan(0);
  });

  it('reset propが変更された時にウィジェットがリセットされる', () => {
    const widgetId = 'test-widget-id';
    mockTurnstileRender.mockReturnValue(widgetId);

    const { rerender } = render(
      <TurnstileWidget siteKey={testSiteKey} onVerify={mockOnVerify} onError={mockOnError} reset={0} />
    );

    // ウィジェットがレンダーされるまで待つ
    expect(mockTurnstileRender).toHaveBeenCalled();

    // reset propを変更
    rerender(<TurnstileWidget siteKey={testSiteKey} onVerify={mockOnVerify} onError={mockOnError} reset={1} />);

    expect(mockTurnstileReset).toHaveBeenCalledWith(widgetId);
  });

  it('テーマ設定が正しく適用される', () => {
    mockTurnstileRender.mockReturnValue('widget-id');

    render(
      <TurnstileWidget
        siteKey={testSiteKey}
        onVerify={mockOnVerify}
        onError={mockOnError}
        theme="dark"
        size="compact"
      />
    );

    expect(mockTurnstileRender).toHaveBeenCalledWith(
      expect.any(Element),
      expect.objectContaining({
        theme: 'dark',
        size: 'compact',
      })
    );
  });

  it('デフォルト設定が正しく適用される', () => {
    mockTurnstileRender.mockReturnValue('widget-id');

    render(
      <TurnstileWidget
        siteKey={testSiteKey}
        onVerify={mockOnVerify}
        onError={mockOnError}
      />
    );

    expect(mockTurnstileRender).toHaveBeenCalledWith(
      expect.any(Element),
      expect.objectContaining({
        theme: 'auto',
        size: 'normal',
      })
    );
  });

  it('onExpireコールバックが正しく設定される', () => {
    const mockOnExpire = vi.fn();
    mockTurnstileRender.mockReturnValue('widget-id');

    render(
      <TurnstileWidget
        siteKey={testSiteKey}
        onVerify={mockOnVerify}
        onError={mockOnError}
        onExpire={mockOnExpire}
      />
    );

    expect(mockTurnstileRender).toHaveBeenCalledWith(
      expect.any(Element),
      expect.objectContaining({
        'expired-callback': expect.any(Function),
      })
    );
  });
});