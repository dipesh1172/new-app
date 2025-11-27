<template>
  <div id='gbarchart'>
  <GChart
    :settings="{packages: ['bar']}"    
    :data="chartData"
    :options="chartOptions"
    :createChart="(el, google) => new google.charts.Bar(el)"
    @ready="onChartReady"
  />
  </div>
</template>

<script>
import { GChart } from 'vue-google-charts'
export default {
  name: 'App',
  components: {
    GChart
  },
  props:{
      chartData: {
        type: Array,
        default: () => null,
      },
      colors: {
        type: Array,
        default: () => ['#ed8b00'],
      }
  },
  data () {
    return {
      chartsLib: null, 
    }
  },
  computed: {
    chartOptions () {
      if (!this.chartsLib) return null
      return this.chartsLib.charts.Bar.convertOptions({
        bars: 'horizontal', // Required for Material Bar Charts.
        height: 400,
        colors: this.colors
      })
    }
  },
  methods: {
    onChartReady (chart, google) {
      this.chartsLib = google
    }
  }
}
</script>
