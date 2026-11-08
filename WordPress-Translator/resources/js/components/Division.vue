<template>
    <div>
        <button class="btn btn-primary mb-3" @click="generateQuestions">
            新しい問題を生成
        </button>
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
                    <th v-if="showResults">商</th>
                    <th v-if="showResults">余り</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(question, index) in questions" :key="index">
                    <td>{{ question.dividend }} ÷ {{ question.divisor }}</td>
                    <td v-if="showResults">{{ question.quotient }}</td>
                    <td v-if="showResults">{{ question.remainder }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
export default {
    data() {
        return {
            questions: [],
            showResults: false,
        };
    },
    methods: {
        generateQuestions() {
            this.questions = Array.from({ length: 10 }, () => {
                let dividend = Math.floor(Math.random() * 90) + 10;
                let divisor = Math.floor(Math.random() * 9) + 1;
                return {
                    dividend,
                    divisor,
                    quotient: Math.floor(dividend / divisor),
                    remainder: dividend % divisor,
                };
            });
        },
    },
    mounted() {
        this.generateQuestions();
    },
};
</script>

<style scoped>
th,
td {
    text-align: center;
    padding: 0.5rem;
}
</style>
