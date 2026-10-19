<script setup>
defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 18 },
})

const paths = {
  board: ['M3 4h18v16H3z', 'M9 4v16', 'M15 4v16'],
  columns: ['M4 4h16v16H4z', 'M12 4v16'],
  statuses: ['M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z', 'M12 8v4l3 2'],
  transitions: ['M4 12h16', 'M14 6l6 6-6 6'],
  issues: ['M4 5h16v14H4z', 'M8 9h8', 'M8 13h5'],
  agents: ['M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z', 'M4 20c0-3.3 3.6-6 8-6s8 2.7 8 6'],
  roles: ['M12 3l8 4v6c0 4-3.5 7-8 8-4.5-1-8-4-8-8V7z'],
  profiles: ['M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z'],
  experiences: ['M7 4h10v16l-5-3-5 3z'],
  plus: ['M12 5v14', 'M5 12h14'],
  chevron: ['M9 6l6 6-6 6'],
  sun: ['M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z', 'M12 3v2', 'M12 19v2', 'M3 12h2', 'M19 12h2', 'M5.6 5.6l1.4 1.4', 'M17 17l1.4 1.4', 'M18.4 5.6L17 7', 'M7 17l-1.4 1.4'],
  moon: ['M20 14.5A8 8 0 1 1 9.5 4 6.5 6.5 0 0 0 20 14.5z'],
  close: ['M6 6l12 12', 'M18 6L6 18'],
  menu: ['M4 7h16', 'M4 12h16', 'M4 17h16'],
  comment: ['M21 12a8 8 0 0 1-8 8H7l-4 3V12a8 8 0 0 1 8-8h2a8 8 0 0 1 8 8z'],
  warning: ['M12 4l9 16H3z', 'M12 10v4', 'M12 17h.01'],
  drag: ['M8 9h8', 'M8 13h8', 'M8 17h8'],
  flask: ['M9 3h6', 'M10 3v6l-5 9a2 2 0 0 0 1.8 3h10.4A2 2 0 0 0 19 18l-5-9V3'],
  smartboard: ['M3 5h18v14H3z', 'M9 5v14', 'M15 5v14', 'M18.2 8.2l.7 1.5 1.5.7-1.5.7-.7 1.5-.7-1.5-1.5-.7 1.5-.7z'],
  edit: ['M4 20h4L19 9l-4-4L4 16z', 'M14 6l4 4'],
  workspace: ['M3 5h18v14H3z', 'M3 10h18'],
  folder: ['M3 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'],
  layers: ['M12 3l9 5-9 5-9-5z', 'M3 13l9 5 9-5'],
  sidebar: ['M3 3h4v18H3z', 'M10 3h11v18H10z'],
  copilot: ['M17 3h4v18h-4z', 'M3 3h11v18H3z'],
}
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <path v-for="(d, index) in paths[name] || []" :key="index" :d="d" />
  </svg>
</template>
