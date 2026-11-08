import { createWebHistory, createRouter } from "vue-router";
import Multiplication from "./components/Multiplication.vue";
import Division from "./components/Division.vue";
import MathEasyQuestions from "./components/MathEasyQuestions.vue";
import MathQuestions from "./components/MathQuestions.vue";

const routes = [
    { path: "/multiplication", component: Multiplication },
    { path: "/division", component: Division },
    { path: "/matheasyquestions", component: MathEasyQuestions },
    { path: "/mathquestions", component: MathQuestions },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});
export default router;
