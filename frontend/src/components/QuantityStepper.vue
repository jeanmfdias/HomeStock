<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const model = defineModel<string>({ required: true })

const props = withDefaults(
  defineProps<{
    step?: number
    min?: number
    inputId?: string
  }>(),
  {
    step: 1,
    min: 0,
    inputId: undefined,
  },
)

const { t } = useI18n()

function adjust(direction: 1 | -1) {
  const current = Number.parseFloat(model.value || '0')
  const next = Math.max(props.min, current + props.step * direction)
  model.value = Number.isInteger(next)
    ? String(next)
    : next.toFixed(3).replace(/0+$/, '').replace(/\.$/, '')
}
</script>

<template>
  <div class="quantity-stepper">
    <button type="button" :aria-label="t('products.decrease')" @click="adjust(-1)">-</button>
    <input :id="inputId" v-model="model" inputmode="decimal" />
    <button type="button" :aria-label="t('products.increase')" @click="adjust(1)">+</button>
  </div>
</template>
