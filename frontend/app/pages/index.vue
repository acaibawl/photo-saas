<script setup lang="ts">
const { public: { contactEmail } } = useRuntimeConfig()

const features = [
  {
    icon: 'i-lucide-shield-check',
    title: '安心の閲覧・購入制限',
    description: '保護者が閲覧・購入できるのは、自分の子どもが写っている写真だけ。他の園児の写真が見えてしまう心配はありません。',
  },
  {
    icon: 'i-lucide-qr-code',
    title: 'QRコードで簡単招待',
    description: '園児ごとにQRコードを発行するだけで、保護者はスマホで読み取ってすぐに登録完了。難しい設定は不要です。',
  },
  {
    icon: 'i-lucide-smartphone',
    title: 'スマホで見て、その場で購入',
    description: '保護者はスマホから写真を閲覧し、その場でオンライン決済。園側は現金回収や集金の手間がなくなります。',
  },
  {
    icon: 'i-lucide-banknote',
    title: '売上は園の口座に直接入金',
    description: 'Stripeを利用し、写真の売上は運営者を介さず園専用の口座へ直接入金されます。',
  },
]

const steps = [
  { title: '園児・クラスを登録', description: '在園児とクラスを登録するだけで準備完了です。' },
  { title: '招待QRコードを配布', description: '園児ごとにQRコードを発行し、お便りやプリントで保護者に配布します。' },
  { title: '写真をアップロード', description: '行事や日常の写真をアップロードすると、自動でプレビュー画像が生成されます。' },
  { title: '保護者が閲覧・購入', description: '保護者は招待コードで登録後、対象の写真を閲覧・購入できます。' },
]
</script>

<template>
  <main class="bg-white text-slate-900">
    <!-- Hero -->
    <section class="bg-gradient-to-b from-sky-50 to-white">
      <div class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:items-center lg:py-24">
        <div class="space-y-6 text-center lg:text-left">
          <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-600">
            保育園・幼稚園向け 写真販売サービス「そだちアルバム」
          </p>
          <h1 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl">
            そだちアルバムで、<br class="hidden sm:block">行事の写真販売をもっとかんたん・安心に。
          </h1>
          <p class="text-base leading-relaxed text-slate-600 sm:text-lg">
            写真の印刷・仕分け・集金といった先生方の負担をなくし、保護者はスマホで我が子の写真を見て、その場で購入できます。
          </p>
          <div class="flex flex-col justify-center gap-3 sm:flex-row lg:justify-start">
            <UButton
              :to="`mailto:${contactEmail}`"
              size="xl"
              color="neutral"
              variant="outline"
            >
              導入のご相談・お問い合わせ
            </UButton>
          </div>
        </div>
        <div>
          <img
            src="/landing_top.png"
            alt="スマートフォンで園の写真を閲覧・購入するイメージ"
            class="w-full rounded-2xl shadow-xl"
          >
        </div>
      </div>
    </section>

    <!-- Features -->
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
      <div class="mx-auto max-w-2xl text-center">
        <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
          選ばれる理由
        </h2>
        <p class="mt-3 text-slate-600">
          園の先生と保護者の双方にとって、負担の少ない写真販売の仕組みです。
        </p>
      </div>

      <div class="mt-10 grid gap-6 sm:grid-cols-2">
        <div
          v-for="feature in features"
          :key="feature.title"
          class="rounded-xl border border-slate-200 p-6"
        >
          <div class="flex items-center justify-center rounded-lg bg-sky-100 text-sky-600 size-10">
            <UIcon :name="feature.icon" class="size-5" />
          </div>
          <h3 class="mt-4 text-lg font-semibold text-slate-900">
            {{ feature.title }}
          </h3>
          <p class="mt-2 text-sm leading-relaxed text-slate-600">
            {{ feature.description }}
          </p>
        </div>
      </div>
    </section>

    <!-- How it works -->
    <section class="bg-slate-50">
      <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="mx-auto max-w-2xl text-center">
          <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
            導入の流れ
          </h2>
          <p class="mt-3 text-slate-600">
            むずかしい設定は不要。すぐに写真販売を始められます。
          </p>
        </div>

        <ol class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <li
            v-for="(step, index) in steps"
            :key="step.title"
            class="rounded-xl bg-white p-6 shadow-sm"
          >
            <span class="flex size-8 items-center justify-center rounded-full bg-sky-600 text-sm font-semibold text-white">
              {{ index + 1 }}
            </span>
            <h3 class="mt-4 text-base font-semibold text-slate-900">
              {{ step.title }}
            </h3>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
              {{ step.description }}
            </p>
          </li>
        </ol>
      </div>
    </section>

    <!-- CTA -->
    <section class="mx-auto max-w-6xl px-4 py-16 text-center sm:px-6">
      <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
        「そだちアルバム」の導入は、お気軽にご相談ください
      </h2>
      <p class="mx-auto mt-3 max-w-xl text-slate-600">
        導入費用や運用方法について、園の状況に合わせてご案内いたします。
      </p>
      <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
        <UButton
          :to="`mailto:${contactEmail}`"
          size="xl"
          color="primary"
          trailing-icon="i-lucide-mail"
        >
          お問い合わせ
        </UButton>
      </div>
    </section>
  </main>
</template>
