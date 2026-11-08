<template>
    <button class="btn btn-primary mb-3" @click="generateQuestions">
        新しい問題を生成
    </button>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>式</th>
                    <th>商</th>
                    <th>余り</th>
                    <th>正解</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(question, index) in questions" :key="index">
                    <td>
                        {{ question.operand1 }} {{ question.operator }}
                        {{ question.operand2 }} =
                    </td>
                    <td>
                        <input
                            type="number"
                            v-model.number="question.userAnswer"
                            class="form-control"
                        />
                    </td>
                    <td>
                        <input
                            type="number"
                            v-model.number="question.userRemainder"
                            class="form-control"
                            v-if="question.operator === '÷'"
                        />
                        <span v-else> - </span>
                    </td>
                    <td>
                        <span v-if="question.answered">
                            {{
                                question.correct
                                    ? "✅"
                                    : "❌ (" +
                                      question.answer +
                                      (question.operator === "÷"
                                          ? " 余り " + question.remainder
                                          : "") +
                                      ")"
                            }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <button class="btn btn-success mt-3" @click="checkAnswers">採点する</button>
    <p class="mt-3 bg-white">正解数: {{ score }}/100</p>
</template>

<script>
export default {
    data() {
        return {
            questions: [],
            score: 0,
        };
    },
    methods: {
        generateQuestions() {
            this.questions = Array.from({ length: 100 }, () => {
                let operand1 = Math.floor(Math.random() * 90) + 10;
                let operand2 = Math.floor(Math.random() * 9) + 1;
                let isMultiplication = Math.random() > 0.5;
                let operator = isMultiplication ? "×" : "÷";
                let answer = isMultiplication
                    ? operand1 * operand2
                    : Math.floor(operand1 / operand2);
                let remainder = isMultiplication ? 0 : operand1 % operand2;
                return {
                    operand1,
                    operand2,
                    operator,
                    answer,
                    remainder,
                    userAnswer: null,
                    userRemainder: null,
                    correct: false,
                    answered: false,
                };
            });
            this.score = 0;
        },
        checkAnswers() {
            this.score = this.questions.reduce((score, question) => {
                question.answered = true;
                if (question.operator === "×") {
                    question.correct = question.userAnswer === question.answer;
                } else {
                    question.correct =
                        question.userAnswer === question.answer &&
                        question.userRemainder === question.remainder;
                }
                return score + (question.correct ? 1 : 0);
            }, 0);
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
    vertical-align: middle;
    padding: 0.5rem;
}

input {
    text-align: center;
    width: 100%;
    min-width: 60px;
}

@media (max-width: 576px) {
    th,
    td {
        font-size: 0.85rem;
        padding: 0.25rem;
    }
}
</style>
