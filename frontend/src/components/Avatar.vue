<script setup>
defineProps({
  name: { type: String, default: '' },
  image: { type: String, default: null },
  color: { type: String, default: null },
  size: { type: String, default: 'md' }, // sm, md, lg
  decorative: { type: Boolean, default: false },
})

function getInitials(name) {
  if (!name) return ''
  return name
    .split(' ')
    .map((part) => part.charAt(0))
    .join('')
    .substring(0, 2)
    .toUpperCase()
}
</script>

<template>
  <div 
    class="avatar" 
    :class="[size, { decorative }]"
    :style="{ 
      background: color || 'var(--surface-3)',
      width: size === 'sm' ? '24px' : size === 'lg' ? '48px' : '32px',
      height: size === 'sm' ? '24px' : size === 'lg' ? '48px' : '32px',
      fontSize: size === 'sm' ? '12px' : size === 'lg' ? '20px' : '14px'
    }"
  >
    <img 
      v-if="image" 
      :src="image" 
      :alt="decorative ? '' : name"
      @error="$event.target.style.display = 'none'"
    />
    <span v-else-if="name" class="initials">{{ getInitials(name) }}</span>
    <span v-else class="initials">?</span>
  </div>
</template>

<style scoped>
.avatar {
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  overflow: hidden;
  flex-shrink: 0;
  position: relative;
}

.avatar.sm {
  width: 24px;
  height: 24px;
  font-size: 12px;
}

.avatar.md {
  width: 32px;
  height: 32px;
  font-size: 14px;
}

.avatar.lg {
  width: 48px;
  height: 48px;
  font-size: 20px;
}

.avatar.decorative {
  /* Não é necessário uma aria-label para elementos decorativos */
}

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.initials {
  user-select: none;
}
</style>