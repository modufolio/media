<template>
  <div class="w-full">
    <video
      ref="videoEl"
      :src="src"
      playsinline
      controls
    />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import Plyr from 'plyr'

const props = defineProps({
  src: { type: String, required: true },
  mimeType: { type: String, default: '' },
})

const videoEl = ref(null)
let player = null

const togglePlayback = () => {
  if (!player) return
  if (player.playing) {
    player.pause()
    return
  }
  void player.play()
}

defineExpose({
  togglePlayback,
})

onMounted(() => {
  if (!videoEl.value) return
  player = new Plyr(videoEl.value, {
    controls: ['play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'fullscreen'],
    hideControls: false,
    resetOnEnd: false,
  })
})

onUnmounted(() => {
  player?.destroy()
  player = null
})

watch(() => [props.src, props.mimeType], () => {
  if (!player) return
  player.source = {
    type: 'video',
    sources: [{ src: props.src, type: props.mimeType || 'video/mp4' }],
  }
})
</script>
