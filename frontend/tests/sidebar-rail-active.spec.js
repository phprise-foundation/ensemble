import { beforeEach, describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { flushPromises, mount } from '@vue/test-utils'
import AppSidebar from '../src/components/AppSidebar.vue'
import { router as appRouter } from '../src/router.js'
import { boardStore } from '../src/store/board.js'

const workspaceId = 'default'

const workspace = {
  workspaces: [{ id: workspaceId, name: 'Default' }],
  boards: [
    { id: 'KANB', name: 'Kanban', workspaceId, workflowId: 'standard', filters: { statusIds: [] }, columns: [] },
    { id: 'B2', name: 'Board 2', workspaceId, workflowId: 'standard', filters: { statusIds: [] }, columns: [] },
  ],
  workflows: [
    { id: 'standard', name: 'Standard', workspaceId },
    { id: 'flow-b', name: 'Flow B', workspaceId },
  ],
  teams: [
    { id: 'default', name: 'Default team', workspaceId },
    { id: 'design', name: 'Design team', workspaceId },
  ],
}

function replaceState(collection, entities) {
  boardStore.state[collection].splice(0, boardStore.state[collection].length, ...entities)
}

async function mountAt(path) {
  const router = createRouter({ history: createMemoryHistory(), routes: appRouter.options.routes })
  await router.push(path)
  await router.isReady()
  const wrapper = mount(AppSidebar, { props: { collapsed: true }, global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

function currentTitles(wrapper) {
  return wrapper.findAll('nav.rail a.rail-leaf[aria-current="page"]').map((link) => link.attributes('title'))
}

function railLink(wrapper, title) {
  return wrapper.findAll('nav.rail a.rail-leaf').find((link) => link.attributes('title') === title)
}

describe('rail colapsado com mais de uma entidade do mesmo tipo', () => {
  beforeEach(() => {
    for (const [collection, entities] of Object.entries(workspace)) {
      replaceState(collection, entities)
    }
  })

  it('destaca somente o board atual quando a loja tem dois boards', async () => {
    const first = await mountAt('/boards/KANB')
    expect(currentTitles(first)).toEqual(['Kanban'])
    expect(first.findAll('nav.rail a.rail-leaf')).toHaveLength(15)
    first.unmount()

    const second = await mountAt('/boards/B2')
    expect(currentTitles(second)).toEqual(['Board 2'])
    second.unmount()
  })

  it('destaca somente a página do workflow atual quando a loja tem dois workflows', async () => {
    const wrapper = await mountAt('/workflows/flow-b/transitions')
    expect(currentTitles(wrapper)).toEqual(['Flow B Transitions'])
    wrapper.unmount()
  })

  it('destaca somente a página do time atual quando a loja tem dois times', async () => {
    const wrapper = await mountAt('/teams/design/profiles')
    expect(currentTitles(wrapper)).toEqual(['Design team Profiles'])
    wrapper.unmount()
  })

  it('move o destaque para o único link atual ao clicar no rail', async () => {
    const wrapper = await mountAt('/teams/default/agents')
    expect(currentTitles(wrapper)).toEqual(['Default team Agents'])
    await railLink(wrapper, 'Experiences').trigger('click')
    await flushPromises()
    expect(currentTitles(wrapper)).toEqual(['Experiences'])
    wrapper.unmount()
  })
})
