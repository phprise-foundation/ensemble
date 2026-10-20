<script setup>
import { computed } from 'vue'
import {
  Kanban,
  AppWindow,
  Folder,
  Layers,
  Users,
  Shield,
  Star,
  Clock,
  ArrowRight,
  FileText,
  Bookmark,
  Plus,
  ChevronRight,
  Sun,
  Moon,
  X,
  Trash2,
  Pencil,
  Check,
  Menu,
  MessageSquare,
  TriangleAlert,
  PanelLeft,
  PanelRight,
  SquareTerminal,
  Info,
} from 'lucide-vue-next'

const props = defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 18 },
})

const icons = {
  board: Kanban,
  workspace: AppWindow,
  folder: Folder,
  layers: Layers,
  agents: Users,
  roles: Shield,
  profiles: Star,
  statuses: Clock,
  transitions: ArrowRight,
  issues: FileText,
  experiences: Bookmark,
  plus: Plus,
  chevron: ChevronRight,
  sun: Sun,
  moon: Moon,
  close: X,
  delete: Trash2,
  edit: Pencil,
  check: Check,
  menu: Menu,
  comment: MessageSquare,
  warning: TriangleAlert,
  sidebar: PanelLeft,
  copilot: PanelRight,
  console: SquareTerminal,
  info: Info,
}

const component = computed(() => icons[props.name])
const isBrand = computed(() => props.name === 'smartboard')
</script>

<template>
  <component
    :is="component"
    v-if="component"
    :size="size"
    stroke-width="2"
    fill="none"
    stroke="currentColor"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  />
  <svg
    v-else-if="isBrand"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <path d="M3 5h18v14H3z" />
    <path d="M9 5v14" />
    <path d="M15 5v14" />
    <path d="M18.2 8.2l.7 1.5 1.5.7-1.5.7-.7 1.5-.7-1.5-1.5-.7 1.5-.7z" />
  </svg>
</template>
