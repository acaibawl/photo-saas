type FieldErrors = Record<string, string[]>

type ApiErrorBody = {
  code?: string
  message?: string
  errors?: Record<string, string[]>
}

type NormalizedApiError = {
  status: number | null
  code: string | null
  message: string
  fieldErrors: FieldErrors
}

function fallbackMessage(status: number | null): string {
  if (status === 401) {
    return '認証に失敗しました。'
  }

  if (status === 403) {
    return 'この操作を行う権限がありません。'
  }

  if (status === 422) {
    return '入力内容を確認してください。'
  }

  if (status === 429) {
    return '試行回数が多すぎます。時間をおいて再試行してください。'
  }

  return '通信エラーが発生しました。しばらくしてから再試行してください。'
}

export function useApiError() {
  const normalizeError = (error: unknown): NormalizedApiError => {
    const maybe = error as {
      statusCode?: number
      response?: {
        status?: number
        _data?: ApiErrorBody
      }
      data?: ApiErrorBody
      message?: string
    }

    const status = maybe.response?.status ?? maybe.statusCode ?? null
    const body = maybe.response?._data ?? maybe.data
    const code = body?.code ?? null

    return {
      status,
      code,
      message: body?.message ?? maybe.message ?? fallbackMessage(status),
      fieldErrors: body?.errors ?? {},
    }
  }

  return {
    normalizeError,
  }
}
