<script setup>
import { computed } from 'vue'
import Avatar from './Avatar.vue'

const props = defineProps({
  comment: { type: Object, required: true },
  authors: { type: Object, required: true }
})

const author = computed(() => {
  if (!props.comment.author) {
    return { name: 'Unknown', avatar: null, avatarColor: '#333333', isUser: false }
  }
  
  return props.authors[props.comment.author] || { 
    name: props.comment.author, 
    avatar: null, 
    avatarColor: '#333333', 
    isUser: false 
  }
})

function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false
  })
}
</script>

<template>
  <div class="comment">
    <Avatar
      :name="author.name"
      :image="author.avatar"
      :color="author.avatarColor"
      size="sm"
      :ring="author.isUser"
    />
    <div class="comment-info">
      <span class="comment-author">{{ author.name }}</span>
      <span class="comment-timestamp muted small">{{ formatDate(comment.at) }}</span>
    </div>
  </div>
</template>

<style scoped>
.comment {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--surface-2);
}

.comment-info {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.comment-author {
  font-weight: 600;
}

.comment-timestamp {
  margin-top: 0.25rem;
}
</style>