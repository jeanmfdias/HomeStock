<script setup lang="ts">
const model = defineModel<string>({ required: true })

const props = withDefaults(
  defineProps<{
    step?: number
    min?: number
  }>(),
  {
    step: 1,
    min: 0,
  },
)

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
    <button type="button" aria-label="Decrease" @click="adjust(-1)">-</button>
    <input v-model="model" inputmode="decimal" />
    <button type="button" aria-label="Increase" @click="adjust(1)">+</button>
  </div>
</template>
