<template>
    <div>
        <div class="mb-3">
            <label for="danSelect" class="form-label">段を選択:</label>
            <select id="danSelect" class="form-select" v-model="selectedDan">
                <option v-for="dan in dans" :key="dan" :value="dan">
                    {{ dan }}の段
                </option>
            </select>
        </div>
        <div class="form-check mb-3">
            <input
                type="checkbox"
                class="form-check-input"
                id="toggleResults"
                v-model="showResults"
            />
            <label class="form-check-label rounded bg-white" for="toggleResults"
                >答えを表示</label
            >
        </div>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>式</th>
                    <th v-if="showResults">結果</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="num in 9" :key="num">
                    <td>{{ selectedDan }} × {{ num }}</td>
                    <td v-if="showResults">{{ selectedDan * num }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { ref } from "vue";

const dans = Array.from({ length: 9 }, (_, i) => i + 1);
const selectedDan = ref(1);
const showResults = ref(false);
</script>

<style scoped>
th,
td {
    text-align: center;
    padding: 0.5rem;
}
</style>
