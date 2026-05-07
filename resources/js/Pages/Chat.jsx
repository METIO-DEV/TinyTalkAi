import { useEffect, useMemo, useRef, useState } from "react"
import {
  ChevronDown,
  FilePlus,
  Folder,
  Loader2,
  Menu,
  MessageSquarePlus,
  Plus,
  Send,
  Settings2,
  Trash2,
  User,
} from "lucide-react"

import { Button } from "@/components/ui/button"
import { Card } from "@/components/ui/card"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Progress } from "@/components/ui/progress"
import { ScrollArea } from "@/components/ui/scroll-area"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet"
import { Switch } from "@/components/ui/switch"
import { Textarea } from "@/components/ui/textarea"
import { cn } from "@/lib/utils"

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? ""
}

async function jsonRequest(url, options = {}) {
  const response = await fetch(url, {
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": csrfToken(),
      ...(options.headers ?? {}),
    },
    ...options,
  })

  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    const message =
      data?.message ??
      Object.values(data?.errors ?? {})?.flat()?.[0] ??
      "Une erreur est survenue."
    throw new Error(message)
  }

  return data
}

async function formRequest(url, body, options = {}) {
  const response = await fetch(url, {
    method: "POST",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "X-CSRF-TOKEN": csrfToken(),
      ...(options.headers ?? {}),
    },
    body,
    ...options,
  })

  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    const message =
      data?.message ??
      Object.values(data?.errors ?? {})?.flat()?.[0] ??
      "Une erreur est survenue."
    throw new Error(message)
  }

  return data
}

function formatSize(bytes) {
  if (!bytes) return "0.00 GB"
  return `${(bytes / 1024 / 1024 / 1024).toFixed(2)} GB`
}

function splitThinkSegments(content) {
  const segments = []
  const regex = /<think>([\s\S]*?)<\/think>/gi
  let lastIndex = 0
  let match

  while ((match = regex.exec(content)) !== null) {
    if (match.index > lastIndex) {
      segments.push({ type: "text", content: content.slice(lastIndex, match.index) })
    }
    segments.push({ type: "think", content: match[1] })
    lastIndex = regex.lastIndex
  }

  if (lastIndex < content.length) {
    segments.push({ type: "text", content: content.slice(lastIndex) })
  }

  return segments.length ? segments : [{ type: "text", content }]
}

export default function Chat({ initialState }) {
  const [state, setState] = useState(initialState)
  const [messages, setMessages] = useState(initialState.messages ?? [])
  const [draft, setDraft] = useState("")
  const [isSending, setIsSending] = useState(false)
  const [isSidebarOpen, setIsSidebarOpen] = useState(false)
  const [isCollectionDialogOpen, setIsCollectionDialogOpen] = useState(false)
  const [newCollectionName, setNewCollectionName] = useState("")
  const [isDocumentDialogOpen, setIsDocumentDialogOpen] = useState(false)
  const [uploadTargetCollection, setUploadTargetCollection] = useState(initialState.selectedCollection ?? "")
  const [documentTitle, setDocumentTitle] = useState("")
  const [documentFile, setDocumentFile] = useState(null)
  const [isUploadingDocument, setIsUploadingDocument] = useState(false)
  const [documentUploadError, setDocumentUploadError] = useState("")
  const [error, setError] = useState("")
  const messagesEndRef = useRef(null)
  const isOllamaAvailable = state.ollama?.available !== false
  const ollamaMessage = state.ollama?.message ?? "Ollama n'est pas accessible."

  useEffect(() => {
    setMessages(initialState.messages ?? [])
  }, [initialState.messages])

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth", block: "end" })
  }, [messages])

  const selectedConversation = useMemo(
    () => state.conversations.find((conversation) => conversation.id === state.selectedConversationId),
    [state.conversations, state.selectedConversationId]
  )

  const tokenPercentage = useMemo(() => {
    if (!state.tokenLimit || !selectedConversation?.tokens) return 0
    return Math.min(100, Math.round((selectedConversation.tokens / state.tokenLimit) * 100))
  }, [selectedConversation?.tokens, state.tokenLimit])

  async function refreshState() {
    const nextState = await jsonRequest("/api/chat/state")
    setState(nextState)
    setMessages(nextState.messages ?? [])
  }

  async function applyStateRequest(request) {
    const nextState = await request()
    setState(nextState)
    setMessages(nextState.messages ?? [])
    return nextState
  }

  async function handleModelChange(model) {
    setError("")
    await applyStateRequest(() =>
      jsonRequest("/api/chat/model", {
        method: "POST",
        body: JSON.stringify({ model }),
      })
    )
  }

  async function handleSelectConversation(conversationId) {
    setError("")
    await applyStateRequest(() =>
      jsonRequest("/api/chat/conversation", {
        method: "POST",
        body: JSON.stringify({ conversationId }),
      })
    )
    setIsSidebarOpen(false)
  }

  async function handleNewConversation() {
    setError("")
    await applyStateRequest(() =>
      jsonRequest("/api/chat/conversation/new", {
        method: "POST",
        body: JSON.stringify({}),
      })
    )
  }

  async function handleDeleteConversation(conversationId) {
    setError("")
    try {
      await applyStateRequest(() =>
        jsonRequest("/api/chat/conversation", {
          method: "DELETE",
          body: JSON.stringify({ conversationId }),
        })
      )
    } catch (exception) {
      setError(exception.message)
    }
  }

  async function handleToggleRag(enabled) {
    setError("")
    await applyStateRequest(() =>
      jsonRequest("/api/chat/rag", {
        method: "POST",
        body: JSON.stringify({ enabled }),
      })
    )
  }

  async function handleSelectCollection(collection) {
    setError("")
    await applyStateRequest(() =>
      jsonRequest("/api/chat/collection", {
        method: "POST",
        body: JSON.stringify({ collection }),
      })
    )
  }

  async function handleCreateCollection(event) {
    event.preventDefault()
    setError("")
    const nextState = await applyStateRequest(() =>
      jsonRequest("/api/chat/collection/create", {
        method: "POST",
        body: JSON.stringify({ name: newCollectionName }),
      })
    )
    setNewCollectionName("")
    setIsCollectionDialogOpen(false)
    return nextState
  }

  async function handleDeleteCollection(collection) {
    setError("")
    await applyStateRequest(() =>
      jsonRequest("/api/chat/collection", {
        method: "DELETE",
        body: JSON.stringify({ collection }),
      })
    )
  }

  function openDocumentDialog(collection = state.selectedCollection) {
    setError("")
    setDocumentUploadError("")
    setUploadTargetCollection(collection ?? "")
    setDocumentTitle("")
    setDocumentFile(null)
    setIsDocumentDialogOpen(true)
  }

  async function handleUploadDocument(event) {
    event.preventDefault()
    setDocumentUploadError("")

    if (!isOllamaAvailable) {
      setDocumentUploadError(ollamaMessage)
      return
    }

    if (!uploadTargetCollection) {
      setDocumentUploadError("Sélectionnez une collection avant d'ajouter un document.")
      return
    }

    if (!documentFile) {
      setDocumentUploadError("Sélectionnez un fichier à ajouter.")
      return
    }

    const body = new FormData()
    body.append("collection", uploadTargetCollection)
    body.append("title", documentTitle)
    body.append("document", documentFile)

    setIsUploadingDocument(true)

    try {
      const nextState = await formRequest("/api/chat/collection/document", body)
      setState(nextState)
      setMessages(nextState.messages ?? [])
      setDocumentTitle("")
      setDocumentFile(null)
      setIsDocumentDialogOpen(false)
    } catch (exception) {
      setDocumentUploadError(exception.message)
    } finally {
      setIsUploadingDocument(false)
    }
  }

  async function handleSummarize() {
    if (!state.selectedConversationId) return
    setError("")
    await applyStateRequest(() =>
      jsonRequest("/api/chat/summarize", {
        method: "POST",
        body: JSON.stringify({ conversationId: state.selectedConversationId }),
      })
    )
  }

  async function submitMessage(event) {
    event.preventDefault()
    const content = draft.trim()
    if (!content || !state.selectedModel || isSending) return

    if (!isOllamaAvailable) {
      setError(ollamaMessage)
      return
    }

    setIsSending(true)
    setError("")
    setDraft("")

    try {
      const prepared = await jsonRequest("/api/chat/prepare", {
        method: "POST",
        body: JSON.stringify({
          message: content,
          model: state.selectedModel,
          conversationId: state.selectedConversationId,
          ragEnabled: state.ragEnabled,
          selectedCollection: state.selectedCollection,
          temperature: state.temperature ?? 0.7,
          maxTokens: state.maxTokens ?? 2048,
        }),
      })

      setState(prepared.state)
      setMessages([...(prepared.state.messages ?? []), { role: "assistant", content: "" }])
      await streamAssistantResponse(prepared.streamPayload)
      await refreshState()
    } catch (exception) {
      setError(exception.message)
      setMessages((current) => [...current, { role: "error", content: exception.message }])
    } finally {
      setIsSending(false)
    }
  }

  async function streamAssistantResponse(payload) {
    const response = await fetch("/api/chat/stream", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "text/event-stream, application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken(),
      },
      body: JSON.stringify(payload),
    })

    if (!response.ok || !response.body) {
      const errorData = await response.json().catch(() => ({}))
      const message =
        errorData?.message ??
        Object.values(errorData?.errors ?? {})?.flat()?.[0] ??
        "Le streaming n'a pas pu démarrer."
      throw new Error(message)
    }

    const reader = response.body.getReader()
    const decoder = new TextDecoder()
    let buffer = ""

    while (true) {
      const { done, value } = await reader.read()
      if (done) break

      buffer += decoder.decode(value, { stream: true })
      const events = buffer.split("\n\n")
      buffer = events.pop() ?? ""

      for (const rawEvent of events) {
        const eventName = rawEvent.match(/^event:\s*(.+)$/m)?.[1]
        const dataLine = rawEvent.match(/^data:\s*(.+)$/m)?.[1]
        if (!eventName || !dataLine) continue

        const data = JSON.parse(dataLine)

        if (eventName === "chunk") {
          setMessages((current) => {
            const next = [...current]
            const last = next[next.length - 1]
            if (last?.role === "assistant") {
              next[next.length - 1] = { ...last, content: `${last.content}${data.content}` }
            }
            return next
          })
        }

        if (eventName === "error") {
          throw new Error(data.message ?? "Erreur pendant le streaming.")
        }
      }
    }
  }

  async function changeLocale() {
    const nextLocale = state.locale === "fr" ? "en" : "fr"
    await fetch("/locale", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken(),
      },
      body: JSON.stringify({ locale: nextLocale }),
    })
    window.location.reload()
  }

  async function logout() {
    await fetch("/logout", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "X-CSRF-TOKEN": csrfToken(),
      },
    })
    window.location.href = "/"
  }

  const sidebar = (
    <Sidebar
      state={state}
      onModelChange={handleModelChange}
      onSelectConversation={handleSelectConversation}
      onNewConversation={handleNewConversation}
      onDeleteConversation={handleDeleteConversation}
      onSelectCollection={handleSelectCollection}
      onCreateCollection={() => setIsCollectionDialogOpen(true)}
      onDeleteCollection={handleDeleteCollection}
      onUploadDocument={openDocumentDialog}
      onChangeLocale={changeLocale}
      onLogout={logout}
    />
  )

  return (
    <div className="flex h-screen overflow-hidden bg-background text-foreground">
      <aside className="hidden w-[21rem] shrink-0 overflow-hidden border-r border-border xl:block">
        {sidebar}
      </aside>

      <main className="flex min-w-0 flex-1 flex-col p-0 xl:p-6">
        <Card className="relative flex min-h-0 flex-1 flex-col overflow-hidden rounded-none border-0 bg-card shadow-none xl:rounded-lg xl:border xl:shadow-xl">
          <div className="absolute left-4 top-4 z-20 xl:hidden">
            <Sheet open={isSidebarOpen} onOpenChange={setIsSidebarOpen}>
              <SheetTrigger asChild>
                <Button variant="outline" size="icon" aria-label="Menu">
                  <Menu />
                </Button>
              </SheetTrigger>
              <SheetContent side="left" className="w-80 p-0">
                {sidebar}
              </SheetContent>
            </Sheet>
          </div>

          {!isOllamaAvailable ? <OllamaUnavailableBanner message={ollamaMessage} url={state.ollama?.url} /> : null}

          <ScrollArea className="min-h-0 flex-1">
            <div className="mx-auto flex min-h-full w-full max-w-5xl flex-col gap-4 px-4 py-6 sm:px-6">
              {state.selectedModel ? (
                <div className="text-center text-lg font-semibold">{state.selectedModel}</div>
              ) : null}

              {messages.length === 0 ? (
                <div className="flex flex-1 items-center justify-center py-20 text-center text-muted-foreground">
                  <div>
                    <p className="text-lg font-medium text-foreground">Bienvenue sur TinyTalkAI</p>
                    <p className="mt-2">Sélectionnez un modèle pour commencer une conversation.</p>
                  </div>
                </div>
              ) : (
                messages.map((message, index) => <MessageBubble key={`${message.role}-${index}`} message={message} />)
              )}
              <div ref={messagesEndRef} />
            </div>
          </ScrollArea>

          <div className="border-t border-border bg-card/95 p-3 sm:p-4">
            {error ? (
              <div className="mb-3 rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {error}
              </div>
            ) : null}

            <form onSubmit={submitMessage} className="flex items-stretch gap-2">
              <Textarea
                value={draft}
                onChange={(event) => setDraft(event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === "Enter" && !event.shiftKey) {
                    event.preventDefault()
                    event.currentTarget.form?.requestSubmit()
                  }
                }}
                rows={1}
                disabled={!state.selectedModel || !isOllamaAvailable || isSending}
                placeholder={
                  !isOllamaAvailable
                    ? "Ollama n'est pas accessible..."
                    : state.selectedModel
                    ? `Écrivez à ${state.selectedModel}... Shift+Enter pour un retour à la ligne`
                    : "Sélectionnez un modèle..."
                }
                className="max-h-44 min-h-10 resize-none rounded-r-none text-sm lg:text-base"
              />
              <Button
                type="submit"
                disabled={!state.selectedModel || !isOllamaAvailable || isSending || !draft.trim()}
                className="h-auto rounded-l-none px-4 sm:px-5"
                aria-label="Envoyer"
              >
                {isSending ? <Loader2 className="animate-spin" /> : <Send />}
              </Button>
              <Button
                type="button"
                variant="outline"
                className="hidden h-auto px-3 lg:inline-flex"
                disabled={!state.selectedCollection || !isOllamaAvailable || isUploadingDocument}
                onClick={() => openDocumentDialog()}
                title={
                  state.selectedCollection
                    ? `Ajouter un document à ${state.selectedCollection}`
                    : "Sélectionnez une collection pour ajouter un document"
                }
                aria-label="Ajouter un document à la collection"
              >
                <FilePlus />
              </Button>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button type="button" variant="outline" size="icon" className="lg:hidden" aria-label="Options">
                    <Settings2 />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem
                    disabled={!state.selectedCollection || !isOllamaAvailable}
                    onClick={() => openDocumentDialog()}
                  >
                    <FilePlus className="mr-2 h-4 w-4" />
                    Document
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </form>

            <div className="mt-3 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <TokenCounter
                selectedModel={state.selectedModel}
                tokensUsed={selectedConversation?.tokens ?? 0}
                tokenLimit={state.tokenLimit}
                percentage={tokenPercentage}
                onSummarize={handleSummarize}
                canSummarize={Boolean(selectedConversation) && isOllamaAvailable}
              />
              <RagControls enabled={state.ragEnabled} onToggle={handleToggleRag} />
            </div>
          </div>
        </Card>
      </main>

      <Dialog open={isCollectionDialogOpen} onOpenChange={setIsCollectionDialogOpen}>
        <DialogContent>
          <form onSubmit={handleCreateCollection}>
            <DialogHeader>
              <DialogTitle>Créer une collection</DialogTitle>
            </DialogHeader>
            <div className="mt-4 space-y-2">
              <Label htmlFor="collection-name">Nom</Label>
              <Input
                id="collection-name"
                value={newCollectionName}
                onChange={(event) => setNewCollectionName(event.target.value)}
                placeholder="ma_collection"
                pattern="[a-z0-9_]+"
                required
              />
              <p className="text-xs text-muted-foreground">
                Lettres minuscules, chiffres et underscores uniquement.
              </p>
            </div>
            <DialogFooter className="mt-6">
              <Button type="button" variant="outline" onClick={() => setIsCollectionDialogOpen(false)}>
                Annuler
              </Button>
              <Button type="submit">Créer</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={isDocumentDialogOpen} onOpenChange={setIsDocumentDialogOpen}>
        <DialogContent>
          <form onSubmit={handleUploadDocument}>
            <DialogHeader>
              <DialogTitle>Ajouter un document</DialogTitle>
              <DialogDescription>
                Le document sera indexé dans la collection sélectionnée.
              </DialogDescription>
            </DialogHeader>
            <div className="mt-4 space-y-4">
              <div className="space-y-2">
                <Label htmlFor="document-collection">Collection</Label>
                <Select value={uploadTargetCollection || undefined} onValueChange={setUploadTargetCollection}>
                  <SelectTrigger id="document-collection">
                    <SelectValue placeholder="Sélectionner une collection" />
                  </SelectTrigger>
                  <SelectContent>
                    {state.collections.map((collection) => (
                      <SelectItem key={collection} value={collection}>
                        {collection}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label htmlFor="document-title">Titre</Label>
                <Input
                  id="document-title"
                  value={documentTitle}
                  onChange={(event) => setDocumentTitle(event.target.value)}
                  placeholder="Nom lisible du document"
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="document-file">Fichier</Label>
                <Input
                  id="document-file"
                  type="file"
                  accept=".txt,.pdf,.docx"
                  onChange={(event) => setDocumentFile(event.target.files?.[0] ?? null)}
                  required
                />
                <p className="text-xs text-muted-foreground">Formats acceptés: txt, pdf, docx. Taille max: 10 MB.</p>
              </div>
              {documentUploadError ? (
                <div className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                  {documentUploadError}
                </div>
              ) : null}
            </div>
            <DialogFooter className="mt-6">
              <Button type="button" variant="outline" onClick={() => setIsDocumentDialogOpen(false)}>
                Annuler
              </Button>
              <Button type="submit" disabled={isUploadingDocument || !isOllamaAvailable || !uploadTargetCollection}>
                {isUploadingDocument ? <Loader2 className="animate-spin" /> : <FilePlus />}
                Ajouter
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}

function OllamaUnavailableBanner({ message, url }) {
  return (
    <div className="border-b border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
      <div className="mx-auto flex w-full max-w-5xl flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <span>{message}</span>
        {url ? <span className="text-xs text-amber-800">Endpoint: {url}</span> : null}
      </div>
    </div>
  )
}

function Sidebar({
  state,
  onModelChange,
  onSelectConversation,
  onNewConversation,
  onDeleteConversation,
  onSelectCollection,
  onCreateCollection,
  onDeleteCollection,
  onUploadDocument,
  onChangeLocale,
  onLogout,
}) {
  return (
    <div className="flex h-full flex-col overflow-hidden bg-background p-5">
      <div className="mb-6 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <img src="/TinyTalkAi_Logo.png" alt="" className="h-10 w-10 rounded-md object-contain" />
          <h1 className="text-lg font-semibold">TinyTalk AI</h1>
        </div>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" size="sm" className="max-w-36">
              <User />
              <span className="hidden truncate 2xl:inline">{state.user?.name}</span>
              <ChevronDown />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onClick={onChangeLocale}>
              {state.locale?.toUpperCase()} / {(state.locale === "fr" ? "en" : "fr").toUpperCase()}
            </DropdownMenuItem>
            <DropdownMenuItem asChild>
              <a href="/profile">Profile</a>
            </DropdownMenuItem>
            {state.user?.isAdmin ? (
              <DropdownMenuItem asChild>
                <a href="/admin">Administration</a>
              </DropdownMenuItem>
            ) : null}
            <DropdownMenuItem className="text-destructive" onClick={onLogout}>
              Log Out
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>

      <SidebarSection title="Models">
        {state.models.length ? (
          <Select value={state.selectedModel || undefined} onValueChange={onModelChange}>
            <SelectTrigger>
              <SelectValue placeholder="Sélectionner un modèle" />
            </SelectTrigger>
            <SelectContent>
              {state.models.map((model) => (
                <SelectItem key={model.name} value={model.name}>
                  {model.label} - {formatSize(model.size)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        ) : (
          <div className="rounded-md border border-border bg-muted px-3 py-2 text-center text-sm text-muted-foreground">
            No models available
          </div>
        )}
      </SidebarSection>

      <SidebarSection
        title="Collections"
        action={
          <div className="flex items-center gap-1">
            <Button
              variant="ghost"
              size="icon"
              disabled={!state.selectedCollection || state.ollama?.available === false}
              onClick={() => onUploadDocument(state.selectedCollection)}
              title={
                state.selectedCollection
                  ? `Ajouter un document à ${state.selectedCollection}`
                  : "Sélectionnez une collection"
              }
              aria-label="Ajouter un document à la collection"
            >
              <FilePlus />
            </Button>
            <Button variant="ghost" size="icon" onClick={onCreateCollection} aria-label="Ajouter une collection">
              <Plus />
            </Button>
          </div>
        }
      >
        <div className="space-y-2">
          {state.collections.length ? (
            state.collections.map((collection) => (
              <div
                key={collection}
                className={cn(
                  "flex items-center gap-1 rounded-lg border border-border p-1",
                  state.selectedCollection === collection ? "bg-accent" : "bg-card"
                )}
              >
                <Button
                  variant="ghost"
                  className="min-w-0 flex-1 justify-start px-2"
                  onClick={() => onSelectCollection(state.selectedCollection === collection ? null : collection)}
                >
                  <Folder />
                  <span className="truncate">{collection}</span>
                </Button>
                <Button
                  variant="ghost"
                  size="icon"
                  className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                  onClick={() => onDeleteCollection(collection)}
                  aria-label="Supprimer la collection"
                >
                  <Trash2 />
                </Button>
              </div>
            ))
          ) : (
            <div className="rounded-md border border-dashed border-border px-3 py-6 text-center text-sm text-muted-foreground">
              Aucune collection disponible
            </div>
          )}
        </div>
      </SidebarSection>

      <SidebarSection
        title="Conversation"
        action={
          <Button variant="ghost" size="icon" onClick={onNewConversation} aria-label="Nouvelle conversation">
            <MessageSquarePlus />
          </Button>
        }
        className="min-h-0 flex-1"
      >
        <ScrollArea className="h-full rounded-lg border border-border bg-card p-2">
          {state.conversations.length ? (
            <div className="space-y-1">
              {state.conversations.map((conversation) => (
                <div
                  key={conversation.id}
                  className={cn(
                    "flex items-center gap-1 rounded-md",
                    state.selectedConversationId === conversation.id && "bg-accent"
                  )}
                >
                  <button
                    type="button"
                    className="min-w-0 flex-1 px-3 py-2 text-left"
                    onClick={() => onSelectConversation(conversation.id)}
                  >
                    <div className="truncate text-sm font-medium">
                      {conversation.title ?? `Conversation ${String(conversation.id).slice(0, 8)}`}
                    </div>
                    <div className="truncate text-xs text-muted-foreground">
                      {conversation.modelName ?? "Modèle inconnu"}
                    </div>
                  </button>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                    onClick={(event) => {
                      event.stopPropagation()
                      onDeleteConversation(conversation.id)
                    }}
                    aria-label="Supprimer cette conversation"
                    title="Supprimer cette conversation"
                  >
                    <Trash2 />
                  </Button>
                </div>
              ))}
            </div>
          ) : (
            <div className="px-3 py-2 text-center text-sm text-muted-foreground">
              Aucune conversation enregistrée
            </div>
          )}
        </ScrollArea>
      </SidebarSection>
    </div>
  )
}

function SidebarSection({ title, action, children, className }) {
  return (
    <section className={cn("mb-6 flex flex-col", className)}>
      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-xs font-semibold uppercase text-muted-foreground">{title}</h2>
        {action}
      </div>
      {children}
    </section>
  )
}

function MessageBubble({ message }) {
  if (message.role === "error") {
    return (
      <div className="flex justify-center">
        <div className="max-w-[80%] rounded-lg border border-destructive bg-destructive/10 px-4 py-2 text-sm text-destructive">
          {message.content}
        </div>
      </div>
    )
  }

  const isUser = message.role === "user"

  return (
    <div className={cn("flex", isUser ? "justify-end" : "justify-start")}>
      <div
        className={cn(
          "max-w-[82%] whitespace-pre-wrap px-4 py-2 text-sm leading-relaxed",
          isUser
            ? "rounded-bl-xl rounded-br-sm rounded-tl-xl rounded-tr-xl bg-primary text-primary-foreground"
            : "rounded-bl-sm rounded-br-xl rounded-tl-xl rounded-tr-xl bg-muted text-foreground"
        )}
      >
        {isUser
          ? message.content
          : splitThinkSegments(message.content).map((segment, index) =>
              segment.type === "think" ? (
                <details key={index} className="my-2">
                  <summary className="cursor-pointer text-xs text-muted-foreground">
                    Processus de réflexion
                  </summary>
                  <div className="mt-2 rounded-md border border-border bg-background p-3 text-xs">
                    {segment.content}
                  </div>
                </details>
              ) : (
                <span key={index}>{segment.content}</span>
              )
            )}
      </div>
    </div>
  )
}

function TokenCounter({ selectedModel, tokensUsed, tokenLimit, percentage, onSummarize, canSummarize }) {
  if (!selectedModel) {
    return <div className="text-xs text-muted-foreground">Sélectionnez un modèle pour afficher la limite de tokens</div>
  }

  return (
    <div className="min-w-0 flex-1 text-xs">
      <div className="flex items-center justify-center gap-2 lg:gap-4 xl:gap-8">
        <span className="hidden whitespace-nowrap lg:inline">
          {tokensUsed} / {tokenLimit ?? "?"} tokens
        </span>
        <div className="relative min-w-32 flex-1 lg:max-w-xl">
          <Progress value={percentage} className="h-4 rounded-full bg-muted" />
          <span className="absolute inset-0 flex items-center justify-center text-[10px] font-medium text-foreground lg:hidden">
            {tokensUsed} / {tokenLimit ?? "?"}
          </span>
          <span className="absolute right-2 top-1/2 hidden -translate-y-1/2 text-[10px] font-medium text-primary-foreground lg:inline">
            {percentage}%
          </span>
        </div>
        <Button variant="secondary" size="sm" onClick={onSummarize} disabled={!canSummarize}>
          Résumer
        </Button>
      </div>
    </div>
  )
}

function RagControls({ enabled, onToggle }) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-4 lg:justify-end">
      <div className="hidden min-w-56 lg:block">
        <div className="text-sm font-medium">Mode RAG</div>
        <div className="text-xs text-muted-foreground">Enrichit les réponses avec le contexte des documents</div>
      </div>
      <Switch checked={enabled} onCheckedChange={onToggle} aria-label="Mode RAG" />
    </div>
  )
}
