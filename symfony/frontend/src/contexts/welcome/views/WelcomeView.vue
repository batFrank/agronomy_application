<template>
  <div class="WelcomeView">
    <div class="content">
      <h1>Welcome</h1>
      <p>This is a placeholder welcome page. No authentication required.</p>
    </div>
  </div>
</template>

<script setup></script>

<style scoped lang="scss">
.WelcomeView {
  display: grid;
  place-content: center;
  width: 100svw;
  min-height: calc(100svh - var(--header-height) * 2);
  background-color: var(--color-neutral-darker);

  .content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    width: 300px;
    margin: auto;
    color: var(--color-neutral-darkest);
    border: 1px solid var(--color-neutral-light);
    background-color: var(--color-neutral-lightest);
    border-radius: 3px;
    padding: 2rem 1rem;
    text-align: center;

    h1 {
      font-family: var(--font-primary);
      color: var(--color-primary);
      margin: 0;
    }

    p {
      font-family: var(--font-primary);
      color: var(--text-color);
      margin: 0;
    }
  }
}
</style>
