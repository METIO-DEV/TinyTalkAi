import { useEffect, useMemo, useRef, useState } from "react"
import {
  ArrowDown,
  BrainCircuit,
  Check,
  ChevronDown,
  Copy,
  Download,
  ExternalLink,
  File,
  FilePlus,
  Folder,
  KeyRound,
  Languages,
  Loader2,
  LogOut,
  Menu,
  MessageSquarePlus,
  Plus,
  Shield,
  SlidersHorizontal,
  Send,
  Settings2,
  Square,
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

const UI_TRANSLATIONS = {
  en: {
    Copy: "Copy",
    Copied: "Copied",
    "Connection validated": "API connection validated.",
    "Connection failed": "API connection failed. Check the error below.",
    Optional: "optional",
    "OpenAI optional fields help": "Fill these only if your OpenAI account requires an organization or project scope.",
    "Provider optional fields help": "Fill these only if this provider requires extra account scoping.",
  },
  fr: {
    Copy: "Copier",
    Copied: "Copié",
    "Connection validated": "Connexion API validée.",
    "Connection failed": "Échec du test de connexion API. Vérifiez l'erreur ci-dessous.",
    Optional: "optionnel",
    "OpenAI optional fields help": "Renseignez ces champs uniquement si votre compte OpenAI impose une organisation ou un projet.",
    "Provider optional fields help": "Renseignez ces champs uniquement si ce fournisseur impose un périmètre de compte.",
  },
}

const CONVERSATION_UPLOAD_TARGET = "__conversation__"
const MAX_DOCUMENT_SIZE_BYTES = 10 * 1024 * 1024
const SUPPORTED_DOCUMENT_EXTENSIONS = ["txt", "pdf", "docx"]

function uiText(key) {
  const locale = document.documentElement.lang?.split("-")?.[0] ?? "fr"

  return UI_TRANSLATIONS[locale]?.[key] ?? UI_TRANSLATIONS.fr[key] ?? key
}

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

function formatBytes(bytes) {
  if (!Number.isFinite(bytes) || bytes <= 0) return null

  const units = ["B", "KB", "MB", "GB", "TB"]
  let value = bytes
  let unitIndex = 0

  while (value >= 1024 && unitIndex < units.length - 1) {
    value /= 1024
    unitIndex += 1
  }

  return `${value.toFixed(value >= 100 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`
}

function documentTitleFromFile(file) {
  const filename = file?.name ?? ""
  const withoutExtension = filename
    .replace(/\.[^/.]+$/, "")
    .replace(/[_-]+/g, " ")
    .replace(/\s+/g, " ")
    .trim()
    .slice(0, 255)

  return withoutExtension || filename || "Document"
}

function validateDocumentFile(file) {
  if (!file) return "Aucun fichier détecté."

  const extension = file.name?.split(".").pop()?.toLowerCase()

  if (!SUPPORTED_DOCUMENT_EXTENSIONS.includes(extension)) {
    return "Seuls les fichiers .txt, .pdf et .docx sont acceptés."
  }

  if (file.size > MAX_DOCUMENT_SIZE_BYTES) {
    return "La taille du fichier ne doit pas dépasser 10 MB."
  }

  if (!documentTitleFromFile(file)) {
    return "Le nom du fichier ne permet pas de générer un titre valide."
  }

  return ""
}

function firstClipboardFile(event) {
  const files = Array.from(event.clipboardData?.files ?? [])
  if (files.length) return files[0]

  const items = Array.from(event.clipboardData?.items ?? [])
  const fileItem = items.find((item) => item.kind === "file")

  return fileItem?.getAsFile() ?? null
}

function firstDroppedFile(event) {
  return Array.from(event.dataTransfer?.files ?? [])[0] ?? null
}

function hasDraggedFiles(event) {
  return Array.from(event.dataTransfer?.types ?? []).includes("Files")
}

function modelValue(provider, model) {
  return `${provider || "ollama"}::${model}`
}

function parseModelValue(value) {
  const [provider, ...modelParts] = String(value ?? "").split("::")

  return {
    provider: provider || "ollama",
    model: modelParts.join("::"),
  }
}

function providerLabel(state, provider) {
  if (!provider || provider === "ollama") return "Ollama"

  return state.providers?.[provider]?.label ?? provider.charAt(0).toUpperCase() + provider.slice(1)
}

function textFromParts(parts) {
  return parts
    .filter((part) => part?.type === "text")
    .map((part) => part.text ?? "")
    .join("")
}

function normalizeMessagePart(part) {
  if (typeof part === "string") {
    return part.trim() ? { type: "text", text: part } : null
  }

  if (!part || typeof part !== "object") {
    return null
  }

  const type = String(part.type ?? "text").toLowerCase()

  if (type === "text" || type === "input_text" || type === "output_text") {
    const text = typeof part.text === "string" ? part.text : typeof part.content === "string" ? part.content : ""
    return text || text === "" ? { type: "text", text } : null
  }

  if (type === "image" || type === "input_image" || type === "output_image" || type === "image_generation_call") {
    const url =
      part.url ??
      part.dataUrl ??
      part.image_url?.url ??
      part.image_url ??
      (part.result && typeof part.result === "string" && !part.result.startsWith("http")
        ? `data:${part.mediaType ?? "image/png"};base64,${part.result}`
        : part.result)

    if (!url) return null

    return {
      type: "image",
      url,
      alt: part.alt ?? part.caption ?? part.revised_prompt ?? part.filename ?? "Image générée",
      filename: part.filename ?? part.name ?? null,
      mediaType: part.mediaType ?? part.mimeType ?? part.mime_type ?? "image/png",
      size: Number.isFinite(Number(part.size)) ? Number(part.size) : null,
      fileId: part.fileId ?? part.file_id ?? null,
    }
  }

  if (type === "file" || type === "input_file" || type === "document") {
    const url = part.url ?? part.dataUrl ?? part.file_url?.url ?? part.file_url ?? null

    return {
      type: "file",
      url,
      filename: part.filename ?? part.name ?? part.title ?? part.fileId ?? part.file_id ?? "Document",
      title: part.title ?? null,
      mediaType: part.mediaType ?? part.mimeType ?? part.mime_type ?? null,
      size: Number.isFinite(Number(part.size)) ? Number(part.size) : null,
      fileId: part.fileId ?? part.file_id ?? null,
    }
  }

  if (Array.isArray(part.content)) {
    return null
  }

  if (typeof part.content === "string") {
    return { type: "text", text: part.content }
  }

  return null
}

function normalizeMessage(message) {
  if (!message || typeof message !== "object") return null

  const fallbackTextPart =
    typeof message.content === "string" ? normalizeMessagePart({ type: "text", text: message.content }) : null

  const normalizedParts = Array.isArray(message.parts)
    ? message.parts.map(normalizeMessagePart).filter(Boolean)
    : fallbackTextPart
    ? [fallbackTextPart]
    : []

  const content = typeof message.content === "string" ? message.content : textFromParts(normalizedParts)

  return {
    ...message,
    content,
    parts: normalizedParts,
  }
}

function normalizeMessages(messages) {
  return Array.isArray(messages) ? messages.map(normalizeMessage).filter(Boolean) : []
}

function appendTextChunkToMessage(message, chunk) {
  const normalizedMessage = normalizeMessage(message)
  if (!normalizedMessage) return message

  const parts = [...normalizedMessage.parts]
  const lastPart = parts[parts.length - 1]

  if (lastPart?.type === "text") {
    parts[parts.length - 1] = {
      ...lastPart,
      text: `${lastPart.text ?? ""}${chunk}`,
    }
  } else {
    parts.push({ type: "text", text: chunk })
  }

  return normalizeMessage({
    ...normalizedMessage,
    isLoading: false,
    parts,
  })
}

function splitThinkSegments(content) {
  const segments = []
  const lowerContent = content.toLowerCase()
  let cursor = 0

  while (cursor < content.length) {
    const start = lowerContent.indexOf("<think>", cursor)

    if (start === -1) {
      const text = content.slice(cursor)
      if (text) segments.push({ type: "text", content: text })
      break
    }

    if (start > cursor) {
      segments.push({ type: "text", content: content.slice(cursor, start) })
    }

    const contentStart = start + "<think>".length
    const end = lowerContent.indexOf("</think>", contentStart)

    if (end === -1) {
      segments.push({ type: "think", content: content.slice(contentStart), isStreaming: true })
      break
    }

    segments.push({ type: "think", content: content.slice(contentStart, end), isStreaming: false })
    cursor = end + "</think>".length
  }

  return segments.length ? segments : [{ type: "text", content }]
}

function thinkingPreview(content) {
  const cleanContent = content
    .replace(/```[\s\S]*?```/g, " bloc de code ")
    .replace(/[#>*_`\[\]()]/g, "")
    .replace(/\s+/g, " ")
    .trim()

  if (!cleanContent) return "Voir les détails de la réflexion du modèle."

  return cleanContent.length > 140 ? `${cleanContent.slice(0, 140)}...` : cleanContent
}

function splitMarkdownTableRow(line) {
  const trimmed = line.trim().replace(/^\|/, "").replace(/\|$/, "")

  return trimmed.split("|").map((cell) => cell.trim())
}

function isMarkdownTableSeparator(line) {
  const cells = splitMarkdownTableRow(line)

  return cells.length > 1 && cells.every((cell) => /^:?-{3,}:?$/.test(cell.replace(/\s+/g, "")))
}

function markdownTableAlignments(separatorLine) {
  return splitMarkdownTableRow(separatorLine).map((cell) => {
    const normalized = cell.replace(/\s+/g, "")

    if (normalized.startsWith(":") && normalized.endsWith(":")) return "center"
    if (normalized.endsWith(":")) return "right"

    return "left"
  })
}

function isMarkdownTableStart(lines, index) {
  return Boolean(lines[index]?.includes("|") && lines[index + 1]?.includes("|") && isMarkdownTableSeparator(lines[index + 1]))
}

function markdownFence(line) {
  const match = line.match(/^\s{0,3}(`{3,}|~{3,})\s*([A-Za-z0-9_+#.-]+)?(?:\s+.*)?$/)

  if (!match) return null

  return {
    marker: match[1][0],
    length: match[1].length,
    language: match[2] ?? "",
  }
}

function isClosingMarkdownFence(line, fence) {
  if (!fence) return false

  const escapedMarker = fence.marker === "`" ? "`" : "~"
  const pattern = new RegExp(`^\\s{0,3}${escapedMarker}{${fence.length},}\\s*$`)

  return pattern.test(line)
}

function isMarkdownBlockStart(line) {
  return (
    Boolean(markdownFence(line)) ||
    /^#{1,4}\s+/.test(line) ||
    /^>\s?/.test(line) ||
    /^[-*]\s+/.test(line) ||
    /^\d+\.\s+/.test(line)
  )
}

function parseMarkdownBlocks(content) {
  const lines = content.replace(/\r\n/g, "\n").split("\n")
  const blocks = []
  let index = 0

  while (index < lines.length) {
    const line = lines[index]

    if (!line.trim()) {
      index += 1
      continue
    }

    const fence = markdownFence(line)
    if (fence) {
      const language = fence.language
      const code = []
      index += 1

      while (index < lines.length && !isClosingMarkdownFence(lines[index], fence)) {
        code.push(lines[index])
        index += 1
      }

      if (index < lines.length) index += 1
      blocks.push({ type: "code", language, content: code.join("\n") })
      continue
    }

    if (isMarkdownTableStart(lines, index)) {
      const headers = splitMarkdownTableRow(lines[index])
      const alignments = markdownTableAlignments(lines[index + 1])
      const rows = []
      index += 2

      while (index < lines.length && lines[index].trim() && lines[index].includes("|")) {
        rows.push(splitMarkdownTableRow(lines[index]))
        index += 1
      }

      blocks.push({ type: "table", headers, alignments, rows })
      continue
    }

    const heading = line.match(/^(#{1,4})\s+(.+)$/)
    if (heading) {
      blocks.push({ type: "heading", level: heading[1].length, content: heading[2] })
      index += 1
      continue
    }

    if (/^>\s?/.test(line)) {
      const quote = []
      while (index < lines.length && /^>\s?/.test(lines[index])) {
        quote.push(lines[index].replace(/^>\s?/, ""))
        index += 1
      }
      blocks.push({ type: "quote", content: quote.join("\n") })
      continue
    }

    if (/^[-*]\s+/.test(line)) {
      const items = []
      while (index < lines.length && /^[-*]\s+/.test(lines[index])) {
        items.push(lines[index].replace(/^[-*]\s+/, ""))
        index += 1
      }
      blocks.push({ type: "list", ordered: false, items })
      continue
    }

    if (/^\d+\.\s+/.test(line)) {
      const items = []
      while (index < lines.length && /^\d+\.\s+/.test(lines[index])) {
        items.push(lines[index].replace(/^\d+\.\s+/, ""))
        index += 1
      }
      blocks.push({ type: "list", ordered: true, items })
      continue
    }

    const paragraph = [line]
    index += 1
    while (index < lines.length && lines[index].trim() && !isMarkdownBlockStart(lines[index]) && !isMarkdownTableStart(lines, index)) {
      paragraph.push(lines[index])
      index += 1
    }
    blocks.push({ type: "paragraph", content: paragraph.join("\n") })
  }

  return blocks
}

function renderInlineMarkdown(text, keyPrefix) {
  const parts = []
  const regex = /(\[[^\]]+\]\(https?:\/\/[^\s)]+\)|`[^`]+`|\*\*[^*]+\*\*|\*[^*]+\*)/g
  let lastIndex = 0
  let match

  while ((match = regex.exec(text)) !== null) {
    if (match.index > lastIndex) {
      parts.push(renderLineBreaks(text.slice(lastIndex, match.index), `${keyPrefix}-text-${lastIndex}`))
    }

    const token = match[0]
    if (token.startsWith("`")) {
      parts.push(
        <code key={`${keyPrefix}-code-${match.index}`} className="rounded bg-background/80 px-1.5 py-0.5 font-mono text-[0.92em]">
          {token.slice(1, -1)}
        </code>
      )
    } else if (token.startsWith("**")) {
      parts.push(<strong key={`${keyPrefix}-strong-${match.index}`}>{token.slice(2, -2)}</strong>)
    } else if (token.startsWith("*")) {
      parts.push(<em key={`${keyPrefix}-em-${match.index}`}>{token.slice(1, -1)}</em>)
    } else {
      const link = token.match(/^\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)$/)
      parts.push(
        <a
          key={`${keyPrefix}-link-${match.index}`}
          href={link[2]}
          target="_blank"
          rel="noreferrer"
          className="font-medium underline underline-offset-4"
        >
          {link[1]}
        </a>
      )
    }

    lastIndex = regex.lastIndex
  }

  if (lastIndex < text.length) {
    parts.push(renderLineBreaks(text.slice(lastIndex), `${keyPrefix}-text-${lastIndex}`))
  }

  return parts.flat()
}

function renderLineBreaks(text, keyPrefix) {
  return text.split("\n").flatMap((line, index, lines) =>
    index < lines.length - 1 ? [line, <br key={`${keyPrefix}-br-${index}`} />] : [line]
  )
}

export default function Chat({ initialState }) {
  const [state, setState] = useState(initialState)
  const [messages, setMessages] = useState(() => normalizeMessages(initialState.messages ?? []))
  const [draft, setDraft] = useState("")
  const [isSending, setIsSending] = useState(false)
  const [isSidebarOpen, setIsSidebarOpen] = useState(false)
  const [isSettingsOpen, setIsSettingsOpen] = useState(false)
  const [isCollectionDialogOpen, setIsCollectionDialogOpen] = useState(false)
  const [newCollectionName, setNewCollectionName] = useState("")
  const [isDocumentDialogOpen, setIsDocumentDialogOpen] = useState(false)
  const [uploadTarget, setUploadTarget] = useState(CONVERSATION_UPLOAD_TARGET)
  const [documentTitle, setDocumentTitle] = useState("")
  const [documentFile, setDocumentFile] = useState(null)
  const [isUploadingDocument, setIsUploadingDocument] = useState(false)
  const [documentUploadError, setDocumentUploadError] = useState("")
  const [pastedDocument, setPastedDocument] = useState(null)
  const [pastedDocumentError, setPastedDocumentError] = useState("")
  const [isDraggingDocument, setIsDraggingDocument] = useState(false)
  const [confirmation, setConfirmation] = useState(null)
  const [isConfirmingAction, setIsConfirmingAction] = useState(false)
  const [showRagNotice, setShowRagNotice] = useState(false)
  const initialProviderId = Object.keys(initialState.providers ?? {})[0] ?? "openai"
  const [settingsProvider, setSettingsProvider] = useState(initialProviderId)
  const [providerApiKey, setProviderApiKey] = useState("")
  const [providerOrganizationId, setProviderOrganizationId] = useState(
    initialState.providers?.[initialProviderId]?.organizationId ?? ""
  )
  const [providerProjectId, setProviderProjectId] = useState(initialState.providers?.[initialProviderId]?.projectId ?? "")
  const [isSavingProvider, setIsSavingProvider] = useState(false)
  const [providerValidationMessage, setProviderValidationMessage] = useState(null)
  const [error, setError] = useState("")
  const [showScrollToBottom, setShowScrollToBottom] = useState(false)
  const messagesEndRef = useRef(null)
  const scrollViewportRef = useRef(null)
  const abortControllerRef = useRef(null)
  const shouldStickToBottomRef = useRef(true)
  const dragDepthRef = useRef(0)
  const selectedProvider = state.selectedProvider ?? "ollama"
  const isOllamaAvailable = state.ollama?.available !== false
  const ollamaMessage = state.ollama?.message ?? "Ollama n'est pas accessible."
  const isSelectedProviderAvailable =
    selectedProvider === "ollama" ? isOllamaAvailable : Boolean(state.providers?.[selectedProvider]?.connected)
  const selectedProviderMessage =
    selectedProvider !== "ollama"
      ? `Connectez votre compte ${providerLabel(state, selectedProvider)} dans les paramètres.`
      : ollamaMessage
  const uploadTargetIsConversation = uploadTarget === CONVERSATION_UPLOAD_TARGET
  const uploadDestinationPreview = uploadTargetIsConversation
    ? {
        label: state.selectedConversationId ? "Conversation actuelle" : "Nouvelle conversation",
        documentCount: state.selectedConversationDocumentCount ?? 0,
        titles: state.selectedConversationDocumentTitles ?? [],
      }
    : {
        label: uploadTarget,
        documentCount: state.collectionStats?.[uploadTarget]?.documentCount ?? 0,
        titles: state.collectionStats?.[uploadTarget]?.documentTitles ?? [],
      }

  useEffect(() => {
    setMessages(normalizeMessages(initialState.messages ?? []))
  }, [initialState.messages])

  useEffect(() => {
    setProviderOrganizationId(state.providers?.[settingsProvider]?.organizationId ?? "")
    setProviderProjectId(state.providers?.[settingsProvider]?.projectId ?? "")
    setProviderApiKey("")
    setProviderValidationMessage(null)
  }, [settingsProvider])

  useEffect(() => {
    if (shouldStickToBottomRef.current) {
      scrollToBottom(messages.some((message) => message.isLoading) ? "auto" : "smooth")
    }
  }, [messages])

  useEffect(() => {
    if (!showRagNotice) return undefined

    const timer = window.setTimeout(() => {
      setShowRagNotice(false)
    }, 5000)

    return () => window.clearTimeout(timer)
  }, [showRagNotice])

  const selectedConversation = useMemo(
    () => state.conversations.find((conversation) => conversation.id === state.selectedConversationId),
    [state.conversations, state.selectedConversationId]
  )

  const tokenPercentage = useMemo(() => {
    if (!state.tokenLimit || !selectedConversation?.tokens) return 0
    return Math.min(100, Math.round((selectedConversation.tokens / state.tokenLimit) * 100))
  }, [selectedConversation?.tokens, state.tokenLimit])

  function commitMessages(nextMessages) {
    if (typeof nextMessages === "function") {
      setMessages((current) => normalizeMessages(nextMessages(current)))
      return
    }

    setMessages(normalizeMessages(nextMessages))
  }

  async function refreshState() {
    const nextState = await jsonRequest("/api/chat/state")
    setState(nextState)
    commitMessages(nextState.messages ?? [])
  }

  function scrollToBottom(behavior = "smooth") {
    messagesEndRef.current?.scrollIntoView({ behavior, block: "end" })
    shouldStickToBottomRef.current = true
    setShowScrollToBottom(false)
  }

  function handleScroll(event) {
    const viewport = event.currentTarget
    const distanceFromBottom = viewport.scrollHeight - viewport.scrollTop - viewport.clientHeight
    const isNearBottom = distanceFromBottom < 120

    shouldStickToBottomRef.current = isNearBottom
    setShowScrollToBottom(!isNearBottom)
  }

  function stopAssistantResponse() {
    abortControllerRef.current?.abort()
    abortControllerRef.current = null
    commitMessages((current) => {
      const next = [...current]
      const last = next[next.length - 1]

      if (last?.role === "assistant") {
        next[next.length - 1] = {
          ...last,
          isLoading: false,
          isStopped: true,
        }
      }

      return next
    })
    setIsSending(false)
  }

  async function applyStateRequest(request) {
    const nextState = await request()
    setState(nextState)
    commitMessages(nextState.messages ?? [])
    return nextState
  }

  function triggerRagNotice(nextState = state) {
    if (nextState.ragEnabled) {
      setShowRagNotice(true)
    }
  }

  async function handleModelChange(value) {
    const { provider, model } = parseModelValue(value)
    setError("")
    await applyStateRequest(() =>
      jsonRequest("/api/chat/model", {
        method: "POST",
        body: JSON.stringify({ provider, model }),
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

    const conversation = state.conversations.find((item) => item.id === conversationId)
    if (!conversation) {
      return
    }

    const documentCount = conversation.documentCount ?? 0

    setConfirmation({
      title: "Supprimer cette conversation ?",
      description: conversation.title ?? `Conversation ${conversation.id}`,
      confirmLabel: "Supprimer",
      variant: "destructive",
      consequences: [
        "Tous les messages de cette conversation seront supprimés.",
        documentCount > 0
          ? `${pluralizeDocument(documentCount, "fichier")} ne seront plus liés à cette conversation.`
          : "Aucun fichier n'est lié à cette conversation.",
        state.selectedConversationId === conversationId
          ? "La conversation active sera fermée."
          : null,
      ].filter(Boolean),
      onConfirm: () =>
        applyStateRequest(() =>
          jsonRequest("/api/chat/conversation", {
            method: "DELETE",
            body: JSON.stringify({ conversationId }),
          })
        ),
    })
  }

  async function handleToggleRag(enabled) {
    setError("")
    const nextState = await applyStateRequest(() =>
      jsonRequest("/api/chat/rag", {
        method: "POST",
        body: JSON.stringify({ enabled }),
      })
    )
    if (enabled) {
      triggerRagNotice(nextState)
    }
  }

  async function handleSelectCollection(collection) {
    setError("")
    const wasRagEnabled = state.ragEnabled
    const nextState = await applyStateRequest(() =>
      jsonRequest("/api/chat/collection", {
        method: "POST",
        body: JSON.stringify({ collection }),
      })
    )
    if (nextState.ragEnabled && (!wasRagEnabled || collection)) {
      triggerRagNotice(nextState)
    }
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

    const stats = state.collectionStats?.[collection] ?? {}
    const documentCount = stats.documentCount ?? 0
    const chunkCount = stats.chunkCount ?? 0

    setConfirmation({
      title: `Supprimer la collection "${collection}" ?`,
      description: "Cette suppression concerne la base de connaissances partagée.",
      confirmLabel: "Supprimer",
      variant: "destructive",
      consequences: [
        `La collection Qdrant "${collection}" sera supprimée.`,
        `${pluralizeDocument(documentCount)} et ${chunkCount} extrait${chunkCount > 1 ? "s" : ""} indexé${chunkCount > 1 ? "s" : ""} seront supprimés.`,
        "Les conversations existantes ne seront pas supprimées.",
        state.selectedCollection === collection
          ? "Cette collection sera désélectionnée et le RAG repassera sur les fichiers de conversation si vous le réactivez sans collection."
          : null,
      ].filter(Boolean),
      onConfirm: () =>
        applyStateRequest(() =>
          jsonRequest("/api/chat/collection", {
            method: "DELETE",
            body: JSON.stringify({ collection }),
          })
        ),
    })
  }

  function openDocumentDialog(target = CONVERSATION_UPLOAD_TARGET) {
    setError("")
    setDocumentUploadError("")
    setUploadTarget(target || CONVERSATION_UPLOAD_TARGET)
    setDocumentTitle("")
    setDocumentFile(null)
    setIsDocumentDialogOpen(true)
  }

  async function uploadDocument(file, title, target = CONVERSATION_UPLOAD_TARGET) {
    if (!isOllamaAvailable) {
      throw new Error(ollamaMessage)
    }

    if (target === CONVERSATION_UPLOAD_TARGET && !state.selectedModel) {
      throw new Error("Sélectionnez un modèle avant d'ajouter un document à une conversation.")
    }

    const validationError = validateDocumentFile(file)
    if (validationError) {
      throw new Error(validationError)
    }

    const body = new FormData()
    body.append("title", title || documentTitleFromFile(file))
    body.append("document", file)

    const isConversationUpload = target === CONVERSATION_UPLOAD_TARGET
    const uploadUrl = isConversationUpload ? "/api/chat/conversation/document" : "/api/chat/collection/document"

    if (isConversationUpload) {
      if (state.selectedConversationId) {
        body.append("conversationId", state.selectedConversationId)
      }
      body.append("provider", selectedProvider)
      body.append("model", state.selectedModel)
    } else {
      body.append("collection", target)
    }

    setIsUploadingDocument(true)

    try {
      const nextState = await formRequest(uploadUrl, body)
      setState(nextState)
      commitMessages(nextState.messages ?? [])
      triggerRagNotice(nextState)
      return nextState
    } finally {
      setIsUploadingDocument(false)
    }
  }

  async function handleUploadDocument(event) {
    event.preventDefault()
    setDocumentUploadError("")

    if (!documentFile) {
      setDocumentUploadError("Sélectionnez un fichier à ajouter.")
      return
    }

    try {
      await uploadDocument(documentFile, documentTitle, uploadTarget)
      setDocumentTitle("")
      setDocumentFile(null)
      setIsDocumentDialogOpen(false)
    } catch (exception) {
      setDocumentUploadError(exception.message)
    }
  }

  function handleDraftPaste(event) {
    const file = firstClipboardFile(event)
    if (!file) return

    event.preventDefault()
    stageConversationDocument(file)
  }

  function stageConversationDocument(file) {
    setError("")
    setPastedDocumentError("")

    const validationError = validateDocumentFile(file)

    if (validationError) {
      setPastedDocument(null)
      setPastedDocumentError(validationError)
      return
    }

    setPastedDocument({
      file,
      title: documentTitleFromFile(file),
    })
  }

  function handleComposerDragEnter(event) {
    if (!hasDraggedFiles(event)) return

    event.preventDefault()
    dragDepthRef.current += 1
    setIsDraggingDocument(true)
  }

  function handleComposerDragOver(event) {
    if (!hasDraggedFiles(event)) return

    event.preventDefault()
    event.dataTransfer.dropEffect = "copy"
    setIsDraggingDocument(true)
  }

  function handleComposerDragLeave(event) {
    if (!hasDraggedFiles(event)) return

    event.preventDefault()
    dragDepthRef.current = Math.max(0, dragDepthRef.current - 1)

    if (dragDepthRef.current === 0) {
      setIsDraggingDocument(false)
    }
  }

  function handleComposerDrop(event) {
    if (!hasDraggedFiles(event)) return

    event.preventDefault()
    dragDepthRef.current = 0
    setIsDraggingDocument(false)

    const file = firstDroppedFile(event)
    if (file) {
      stageConversationDocument(file)
    }
  }

  async function submitMessage(event) {
    event.preventDefault()
    const content = draft.trim()
    if (!content || !state.selectedModel || isSending) return

    if (!isSelectedProviderAvailable) {
      setError(selectedProviderMessage)
      return
    }

    setIsSending(true)
    setError("")
    setDraft("")
    shouldStickToBottomRef.current = true
    abortControllerRef.current = new AbortController()

    try {
      let conversationId = state.selectedConversationId
      let ragEnabled = state.ragEnabled
      let selectedCollection = state.selectedCollection

      if (pastedDocument) {
        setPastedDocumentError("")
        const uploadState = await uploadDocument(pastedDocument.file, pastedDocument.title, CONVERSATION_UPLOAD_TARGET)
        conversationId = uploadState.selectedConversationId
        ragEnabled = uploadState.ragEnabled
        selectedCollection = uploadState.selectedCollection
        setPastedDocument(null)
      }

      const prepared = await jsonRequest("/api/chat/prepare", {
        method: "POST",
        signal: abortControllerRef.current.signal,
        body: JSON.stringify({
          message: content,
          provider: selectedProvider,
          model: state.selectedModel,
          conversationId,
          ragEnabled,
          selectedCollection,
          temperature: state.temperature ?? 0.7,
          maxTokens: state.maxTokens ?? 2048,
          reasoningEffort: state.reasoningEffort ?? "medium",
        }),
      })

      setState(prepared.state)
      commitMessages([...(prepared.state.messages ?? []), { role: "assistant", content: "", parts: [], isLoading: true }])
      await streamAssistantResponse(prepared.streamPayload)
      await refreshState()
    } catch (exception) {
      if (exception.name === "AbortError") {
        return
      }

      if (pastedDocument) {
        setDraft(content)
      }
      setError(exception.message)
      commitMessages((current) => [...current, { role: "error", content: exception.message }])
    } finally {
      abortControllerRef.current = null
      setIsSending(false)
    }
  }

  async function streamAssistantResponse(payload) {
    const abortController = abortControllerRef.current ?? new AbortController()
    abortControllerRef.current = abortController

    const response = await fetch("/api/chat/stream", {
      method: "POST",
      credentials: "same-origin",
      signal: abortController.signal,
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
          commitMessages((current) => {
            const next = [...current]
            const last = next[next.length - 1]
            if (last?.role === "assistant") {
              next[next.length - 1] = appendTextChunkToMessage(last, data.content ?? "")
            }
            return next
          })
        }

        if ((eventName === "message" || eventName === "complete") && data.message) {
          commitMessages((current) => {
            const incomingMessage = normalizeMessage(data.message)
            if (!incomingMessage) return current

            const next = [...current]
            const last = next[next.length - 1]

            if (last?.role === "assistant") {
              next[next.length - 1] = {
                ...incomingMessage,
                isLoading: false,
                isStopped: last?.isStopped ?? false,
              }
            } else {
              next.push({ ...incomingMessage, isLoading: false })
            }

            return next
          })
        }

        if (eventName === "part" && data.part) {
          commitMessages((current) => {
            const next = [...current]
            const last = normalizeMessage(next[next.length - 1])

            if (last?.role === "assistant") {
              next[next.length - 1] = normalizeMessage({
                ...last,
                isLoading: false,
                parts: [...(last.parts ?? []), data.part],
              })
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

  async function saveProviderAccount(event) {
    event.preventDefault()
    setError("")
    setProviderValidationMessage(null)
    setIsSavingProvider(true)

    try {
      const nextState = await jsonRequest(`/api/chat/provider-accounts/${settingsProvider}`, {
        method: "POST",
        body: JSON.stringify({
          apiKey: providerApiKey,
          organizationId: providerOrganizationId || null,
          projectId: providerProjectId || null,
        }),
      })
      setState(nextState)
      setProviderApiKey("")
    } catch (exception) {
      setError(exception.message)
    } finally {
      setIsSavingProvider(false)
    }
  }

  async function testProviderAccount() {
    setError("")
    setProviderValidationMessage(null)
    setIsSavingProvider(true)

    try {
      const nextState = await jsonRequest(`/api/chat/provider-accounts/${settingsProvider}/test`, {
        method: "POST",
        body: JSON.stringify({}),
      })
      setState(nextState)
      setProviderValidationMessage({
        type: nextState.providers?.[settingsProvider]?.connected ? "success" : "error",
        text: nextState.providers?.[settingsProvider]?.connected ? uiText("Connection validated") : uiText("Connection failed"),
      })
    } catch (exception) {
      setProviderValidationMessage({
        type: "error",
        text: exception.message,
      })
      setError(exception.message)
    } finally {
      setIsSavingProvider(false)
    }
  }

  async function disconnectProviderAccount() {
    setError("")
    setProviderValidationMessage(null)
    const currentProvider = settingsProvider
    const currentProviderLabel = providerLabel(state, currentProvider)

    setConfirmation({
      title: `Déconnecter ${currentProviderLabel} ?`,
      description: "La clé API enregistrée sera retirée de ce compte utilisateur.",
      confirmLabel: "Déconnecter",
      variant: "destructive",
      consequences: [
        `Les modèles ${currentProviderLabel} ne seront plus disponibles tant qu'une nouvelle clé n'est pas enregistrée.`,
        state.selectedProvider === currentProvider
          ? `Le modèle ${currentProviderLabel} actif et la conversation active seront désélectionnés.`
          : "Les conversations existantes ne seront pas supprimées.",
      ],
      onConfirm: async () => {
        const nextState = await jsonRequest(`/api/chat/provider-accounts/${currentProvider}`, {
          method: "DELETE",
          body: JSON.stringify({}),
        })
        setState(nextState)
        commitMessages(nextState.messages ?? [])
        setProviderApiKey("")
      },
    })
  }

  async function runConfirmedAction() {
    if (!confirmation?.onConfirm) return

    setIsConfirmingAction(true)

    try {
      await confirmation.onConfirm()
      setConfirmation(null)
    } catch (exception) {
      setError(exception.message)
    } finally {
      setIsConfirmingAction(false)
    }
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
      onOpenSettings={() => setIsSettingsOpen(true)}
    />
  )

  return (
    <div className="flex h-dvh overflow-hidden bg-background text-foreground">
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

          {selectedProvider === "ollama" && !isOllamaAvailable ? (
            <OllamaUnavailableBanner message={ollamaMessage} url={state.ollama?.url} />
          ) : null}

          <ScrollArea
            viewportRef={scrollViewportRef}
            onViewportScroll={handleScroll}
            className="min-h-0 flex-1"
          >
            <div className="mx-auto flex min-h-full w-full max-w-5xl flex-col gap-4 px-4 py-6 sm:px-6">
              {state.selectedModel ? (
                <div className="text-center text-lg font-semibold">
                  {selectedProvider !== "ollama" ? `${providerLabel(state, selectedProvider)} · ` : ""}
                  {state.selectedModel}
                </div>
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

          {showScrollToBottom ? (
            <Button
              type="button"
              size="sm"
              className="absolute bottom-28 left-1/2 z-20 -translate-x-1/2 rounded-full shadow-lg"
              onClick={() => scrollToBottom()}
            >
              <ArrowDown className="h-4 w-4" />
              Bas
            </Button>
          ) : null}

          <div
            className={cn(
              "relative border-t border-border bg-card/95 p-3 transition sm:p-4",
              isDraggingDocument && "bg-accent/40"
            )}
            onDragEnter={handleComposerDragEnter}
            onDragOver={handleComposerDragOver}
            onDragLeave={handleComposerDragLeave}
            onDrop={handleComposerDrop}
          >
            {isDraggingDocument ? (
              <div className="pointer-events-none absolute inset-2 z-10 flex items-center justify-center rounded-lg border-2 border-dashed border-primary bg-background/85 text-sm font-medium text-foreground shadow-sm">
                Déposez le document pour l’ajouter au prochain message
              </div>
            ) : null}

            {error ? (
              <div className="mb-3 rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {error}
              </div>
            ) : null}

            <RagContextNotice
              visible={showRagNotice}
              enabled={state.ragEnabled}
              selectedCollection={state.selectedCollection}
              collectionStats={state.collectionStats}
              conversationDocumentCount={state.selectedConversationDocumentCount ?? 0}
            />

            <PastedDocumentPreview
              document={pastedDocument}
              error={pastedDocumentError}
              isUploading={isUploadingDocument}
              onCancel={() => {
                setPastedDocument(null)
                setPastedDocumentError("")
              }}
            />

            <form onSubmit={submitMessage} className="flex items-stretch gap-2">
              <Textarea
                value={draft}
                onChange={(event) => setDraft(event.target.value)}
                onPaste={handleDraftPaste}
                onKeyDown={(event) => {
                  if (event.key === "Enter" && !event.shiftKey) {
                    event.preventDefault()
                    event.currentTarget.form?.requestSubmit()
                  }
                }}
                rows={1}
                disabled={!state.selectedModel || !isSelectedProviderAvailable || isSending}
                placeholder={
                  !isSelectedProviderAvailable
                    ? selectedProviderMessage
                    : state.selectedModel
                    ? `Écrivez à ${state.selectedModel}... Shift+Enter pour un retour à la ligne`
                    : "Sélectionnez un modèle..."
                }
                className="max-h-44 min-h-10 resize-none rounded-r-none text-sm lg:text-base"
              />
              <Button
                type={isSending ? "button" : "submit"}
                variant={isSending ? "destructive" : "default"}
                disabled={!isSending && (!state.selectedModel || !isSelectedProviderAvailable || !draft.trim())}
                onClick={isSending ? stopAssistantResponse : undefined}
                className="h-auto rounded-l-none px-4 sm:px-5"
                aria-label={isSending ? "Stopper la réponse" : "Envoyer"}
              >
                {isSending ? <Square /> : <Send />}
              </Button>
              <Button
                type="button"
                variant="outline"
                className="hidden h-auto px-3 lg:inline-flex"
                disabled={!state.selectedModel || !isOllamaAvailable || isUploadingDocument}
                onClick={() => openDocumentDialog(CONVERSATION_UPLOAD_TARGET)}
                title="Ajouter un document à la conversation"
                aria-label="Ajouter un document à la conversation"
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
                    disabled={!state.selectedModel || !isOllamaAvailable}
                    onClick={() => openDocumentDialog(CONVERSATION_UPLOAD_TARGET)}
                  >
                    <FilePlus className="mr-2 h-4 w-4" />
                    Document conversation
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
              />
              <RagControls
                enabled={state.ragEnabled}
                selectedCollection={state.selectedCollection}
                collectionStats={state.collectionStats}
                conversationDocumentCount={state.selectedConversationDocumentCount ?? 0}
                onToggle={handleToggleRag}
              />
            </div>
          </div>
        </Card>
      </main>

      <SettingsDialog
        open={isSettingsOpen}
        onOpenChange={setIsSettingsOpen}
        state={state}
        settingsProvider={settingsProvider}
        setSettingsProvider={setSettingsProvider}
        providerApiKey={providerApiKey}
        setProviderApiKey={setProviderApiKey}
        providerOrganizationId={providerOrganizationId}
        setProviderOrganizationId={setProviderOrganizationId}
        providerProjectId={providerProjectId}
        setProviderProjectId={setProviderProjectId}
        isSavingProvider={isSavingProvider}
        onSaveProvider={saveProviderAccount}
        onTestProvider={testProviderAccount}
        onDisconnectProvider={disconnectProviderAccount}
        providerValidationMessage={providerValidationMessage}
        onChangeLocale={changeLocale}
        onLogout={logout}
      />

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
                {uploadTargetIsConversation
                  ? "Le document sera disponible uniquement pour cette conversation."
                  : "Le document sera indexé dans la collection sélectionnée."}
              </DialogDescription>
            </DialogHeader>
            <div className="mt-4 space-y-4">
              <div className="space-y-2">
                <Label htmlFor="document-target">Destination</Label>
                <Select value={uploadTarget} onValueChange={setUploadTarget}>
                  <SelectTrigger id="document-target">
                    <SelectValue placeholder="Sélectionner une destination" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={CONVERSATION_UPLOAD_TARGET}>
                      {state.selectedConversationId ? "Conversation actuelle" : "Nouvelle conversation"}
                    </SelectItem>
                    {state.collections.map((collection) => (
                      <SelectItem key={collection} value={collection}>
                        {collection} ({state.collectionStats?.[collection]?.documentCount ?? 0})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                  Sans collection, le RAG interrogera les fichiers liés à la conversation.
                </p>
                <DocumentDestinationPreview preview={uploadDestinationPreview} />
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
              <Button type="submit" disabled={isUploadingDocument || !isOllamaAvailable || !uploadTarget}>
                {isUploadingDocument ? <Loader2 className="animate-spin" /> : <FilePlus />}
                Ajouter
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <ConfirmationDialog
        confirmation={confirmation}
        isPending={isConfirmingAction}
        onCancel={() => setConfirmation(null)}
        onConfirm={runConfirmedAction}
      />
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

function DocumentDestinationPreview({ preview }) {
  if (!preview) {
    return null
  }

  const titles = preview.titles ?? []

  return (
    <div className="rounded-md border border-border bg-muted/40 px-3 py-2 text-xs">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <span className="font-medium text-foreground">{preview.label}</span>
        <span className="rounded-md bg-background px-2 py-0.5 text-muted-foreground ring-1 ring-border">
          {pluralizeDocument(preview.documentCount ?? 0, "fichier")}
        </span>
      </div>
      {titles.length ? (
        <div className="mt-2 space-y-1 text-muted-foreground">
          {titles.map((title, index) => (
            <div key={`${title}-${index}`} className="truncate">
              {index + 1}. {title}
            </div>
          ))}
        </div>
      ) : (
        <div className="mt-2 text-muted-foreground">Aucun document existant dans cette destination.</div>
      )}
    </div>
  )
}

function PastedDocumentPreview({
  document,
  error,
  isUploading,
  onCancel,
}) {
  if (!document && !error) {
    return null
  }

  return (
    <div className="mb-3 rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">
      {document ? (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="min-w-0 flex items-start gap-3">
            <span className="rounded-md bg-background p-2 text-muted-foreground ring-1 ring-border">
              <File className="h-4 w-4" />
            </span>
            <div className="min-w-0">
              <div className="truncate font-medium text-foreground">{document.title}</div>
              <div className="truncate text-xs text-muted-foreground">
                {document.file.name} {formatBytes(document.file.size) ? `· ${formatBytes(document.file.size)}` : ""}
              </div>
              <div className="mt-2 text-xs text-muted-foreground">
                Sera ajouté à la conversation avant l'envoi du prochain message.
              </div>
            </div>
          </div>
          <div className="flex shrink-0 justify-end gap-2">
            <Button type="button" variant="outline" size="sm" onClick={onCancel} disabled={isUploading}>
              Annuler
            </Button>
          </div>
        </div>
      ) : null}
      {error ? (
        <div className={cn("text-sm text-destructive", document && "mt-3 border-t border-border pt-2")}>
          {error}
        </div>
      ) : null}
    </div>
  )
}

function ConfirmationDialog({ confirmation, isPending, onCancel, onConfirm }) {
  const open = Boolean(confirmation)
  const isDestructive = confirmation?.variant === "destructive"

  return (
    <Dialog open={open} onOpenChange={(nextOpen) => !nextOpen && !isPending && onCancel()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{confirmation?.title ?? "Confirmer l'action"}</DialogTitle>
          {confirmation?.description ? (
            <DialogDescription>{confirmation.description}</DialogDescription>
          ) : null}
        </DialogHeader>

        {confirmation?.consequences?.length ? (
          <div className="mt-4 rounded-md border border-border bg-muted/40 p-3 text-sm">
            <div className="mb-2 font-medium text-foreground">Conséquences</div>
            <ul className="space-y-1.5 text-muted-foreground">
              {confirmation.consequences.map((item, index) => (
                <li key={index} className="flex gap-2">
                  <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-current" />
                  <span>{item}</span>
                </li>
              ))}
            </ul>
          </div>
        ) : null}

        <DialogFooter className="mt-6">
          <Button type="button" variant="outline" onClick={onCancel} disabled={isPending}>
            Annuler
          </Button>
          <Button
            type="button"
            variant={isDestructive ? "destructive" : "default"}
            onClick={onConfirm}
            disabled={isPending}
          >
            {isPending ? <Loader2 className="animate-spin" /> : null}
            {confirmation?.confirmLabel ?? "Confirmer"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

function SettingsDialog({
  open,
  onOpenChange,
  state,
  settingsProvider,
  setSettingsProvider,
  providerApiKey,
  setProviderApiKey,
  providerOrganizationId,
  setProviderOrganizationId,
  providerProjectId,
  setProviderProjectId,
  isSavingProvider,
  onSaveProvider,
  onTestProvider,
  onDisconnectProvider,
  providerValidationMessage,
  onChangeLocale,
  onLogout,
}) {
  const providers = Object.values(state.providers ?? {})
  const selectedProviderAccount = state.providers?.[settingsProvider] ?? providers[0] ?? {}
  const selectedProviderLabel = selectedProviderAccount.label ?? providerLabel(state, settingsProvider)
  const selectedProviderStatus = selectedProviderAccount.connected
    ? "Connecté"
    : selectedProviderAccount.status === "error"
    ? "Erreur"
    : "Déconnecté"

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Paramètres</DialogTitle>
          <DialogDescription>Compte, modèles et connexions.</DialogDescription>
        </DialogHeader>

        <div className="grid gap-4 md:grid-cols-2">
          <section className="rounded-lg border border-border p-4">
            <div className="mb-3 flex items-center gap-2 text-sm font-semibold">
              <User className="h-4 w-4" />
              Compte
            </div>
            <div className="space-y-3 text-sm">
              <div>
                <div className="text-xs text-muted-foreground">Utilisateur</div>
                <div className="font-medium">{state.user?.name}</div>
              </div>
              <Button type="button" variant="outline" className="w-full justify-start" onClick={onChangeLocale}>
                <Languages className="h-4 w-4" />
                {state.locale?.toUpperCase()} / {(state.locale === "fr" ? "en" : "fr").toUpperCase()}
              </Button>
              <Button type="button" variant="outline" className="w-full justify-start" asChild>
                <a href="/profile">
                  <Settings2 className="h-4 w-4" />
                  Profil
                </a>
              </Button>
              {state.user?.isAdmin ? (
                <Button type="button" variant="outline" className="w-full justify-start" asChild>
                  <a href="/admin">
                    <Shield className="h-4 w-4" />
                    Administration
                  </a>
                </Button>
              ) : null}
              <Button type="button" variant="destructive" className="w-full justify-start" onClick={onLogout}>
                <LogOut className="h-4 w-4" />
                Déconnexion
              </Button>
            </div>
          </section>

          <section className="rounded-lg border border-border p-4">
            <div className="mb-3 flex items-center gap-2 text-sm font-semibold">
              <SlidersHorizontal className="h-4 w-4" />
              Modèles
            </div>
            <div className="space-y-3 text-sm">
              <div>
                <div className="text-xs text-muted-foreground">Fournisseur actif</div>
                <div className="font-medium">{providerLabel(state, state.selectedProvider)}</div>
              </div>
              <div>
                <div className="text-xs text-muted-foreground">Modèle actif</div>
                <div className="font-medium">{state.selectedModel || "Aucun modèle"}</div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <div className="text-xs text-muted-foreground">Température</div>
                  <div className="font-medium">{state.temperature ?? 0.7}</div>
                </div>
                <div>
                  <div className="text-xs text-muted-foreground">Max tokens</div>
                  <div className="font-medium">{state.maxTokens ?? 2048}</div>
                </div>
              </div>
              <div>
                <div className="text-xs text-muted-foreground">Raisonnement</div>
                <div className="font-medium">{state.reasoningEffort ?? "medium"}</div>
              </div>
            </div>
          </section>
        </div>

        <section className="rounded-lg border border-border p-4">
          <div className="mb-3 flex items-center justify-between gap-3">
            <div className="flex items-center gap-2 text-sm font-semibold">
              <KeyRound className="h-4 w-4" />
              Connexions API
            </div>
            <span className="rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground">{selectedProviderStatus}</span>
          </div>

          <form onSubmit={onSaveProvider} className="space-y-3">
            <div className="grid gap-3 md:grid-cols-2">
              <div className="md:col-span-2">
                <Label htmlFor="provider-id">Fournisseur</Label>
                <Select value={settingsProvider} onValueChange={setSettingsProvider}>
                  <SelectTrigger id="provider-id">
                    <SelectValue placeholder="Choisir un fournisseur" />
                  </SelectTrigger>
                  <SelectContent>
                    {providers.map((provider) => (
                      <SelectItem key={provider.id} value={provider.id}>
                        {provider.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="md:col-span-2">
                <Label htmlFor="provider-key">Clé API</Label>
                <Input
                  id="provider-key"
                  type="password"
                  value={providerApiKey}
                  onChange={(event) => setProviderApiKey(event.target.value)}
                  placeholder={selectedProviderAccount.keyPreview || "clé API"}
                />
              </div>
              {selectedProviderAccount.supportsOrganizationId ? (
                <div>
                  <Label htmlFor="provider-org">Organization ID ({uiText("Optional")})</Label>
                  <Input
                    id="provider-org"
                    value={providerOrganizationId}
                    onChange={(event) => setProviderOrganizationId(event.target.value)}
                    placeholder="org_..."
                  />
                </div>
              ) : null}
              {selectedProviderAccount.supportsProjectId ? (
                <div>
                  <Label htmlFor="provider-project">Project ID ({uiText("Optional")})</Label>
                  <Input
                    id="provider-project"
                    value={providerProjectId}
                    onChange={(event) => setProviderProjectId(event.target.value)}
                    placeholder="proj_..."
                  />
                </div>
              ) : null}
            </div>
            <p className="text-xs leading-5 text-muted-foreground">
              {selectedProviderAccount.supportsOrganizationId || selectedProviderAccount.supportsProjectId
                ? uiText("Provider optional fields help")
                : `${selectedProviderLabel} utilise uniquement la clé API pour ce compte.`}
            </p>

            {selectedProviderAccount.lastError ? (
              <div className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {selectedProviderAccount.lastError}
              </div>
            ) : null}

            {providerValidationMessage ? (
              <div
                className={cn(
                  "rounded-md border px-3 py-2 text-sm",
                  providerValidationMessage.type === "success"
                    ? "border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                    : "border-destructive/40 bg-destructive/10 text-destructive"
                )}
              >
                {providerValidationMessage.text}
              </div>
            ) : null}

            <div className="flex flex-wrap justify-end gap-2">
              {selectedProviderAccount.status !== "disconnected" ? (
                <Button type="button" variant="outline" onClick={onTestProvider} disabled={isSavingProvider}>
                  {isSavingProvider ? <Loader2 className="animate-spin" /> : null}
                  Tester
                </Button>
              ) : null}
              {selectedProviderAccount.status !== "disconnected" ? (
                <Button type="button" variant="outline" onClick={onDisconnectProvider} disabled={isSavingProvider}>
                  Déconnecter
                </Button>
              ) : null}
              <Button type="submit" disabled={isSavingProvider || (!providerApiKey && !selectedProviderAccount.connected)}>
                {isSavingProvider ? <Loader2 className="animate-spin" /> : null}
                Enregistrer
              </Button>
            </div>
          </form>
        </section>
      </DialogContent>
    </Dialog>
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
  onOpenSettings,
}) {
  return (
    <div className="flex h-full flex-col overflow-hidden bg-background p-5">
      <div className="mb-6 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <img src="/TinyTalkAi_Logo.png" alt="" className="h-10 w-10 rounded-md object-contain" />
          <h1 className="text-lg font-semibold">TinyTalk AI</h1>
        </div>
      </div>

      <SidebarSection title="Models">
        {state.models.length ? (
          <Select
            value={state.selectedModel ? modelValue(state.selectedProvider, state.selectedModel) : undefined}
            onValueChange={onModelChange}
          >
            <SelectTrigger>
              <SelectValue placeholder="Sélectionner un modèle" />
            </SelectTrigger>
            <SelectContent>
              {state.models.map((model) => (
                <SelectItem key={modelValue(model.provider, model.name)} value={modelValue(model.provider, model.name)}>
                  {providerLabel(state, model.provider)} - {model.label}
                  {model.size ? ` - ${formatSize(model.size)}` : ""}
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
        className="min-h-0 shrink-0"
      >
        <ScrollArea className="max-h-56 rounded-lg border border-border bg-card">
          <div className="space-y-2 p-2">
            {state.collections.length ? (
              state.collections.map((collection) => {
                const canDeleteCollection = state.deletableCollections?.includes(collection)
                const documentCount = state.collectionStats?.[collection]?.documentCount ?? 0

                return (
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
                      <Folder className="shrink-0" />
                      <span className="min-w-0 flex-1 truncate">{collection}</span>
                      <span className="shrink-0 rounded-md bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground">
                        {documentCount}
                      </span>
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive disabled:opacity-40"
                      disabled={!canDeleteCollection}
                      onClick={() => onDeleteCollection(collection)}
                      aria-label={canDeleteCollection ? "Supprimer la collection" : "Suppression reservee au proprietaire ou a un administrateur"}
                      title={canDeleteCollection ? "Supprimer la collection" : "Suppression reservee au proprietaire ou a un administrateur"}
                    >
                      <Trash2 />
                    </Button>
                  </div>
                )
              })
            ) : (
              <div className="rounded-md border border-dashed border-border px-3 py-6 text-center text-sm text-muted-foreground">
                Aucune collection disponible
              </div>
            )}
          </div>
        </ScrollArea>
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
                    "grid grid-cols-[minmax(0,1fr)_2.25rem] items-center gap-1 rounded-md",
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
                      {conversation.provider && conversation.provider !== "ollama"
                        ? `${providerLabel(state, conversation.provider)} · `
                        : ""}
                      {conversation.modelName ?? "Modèle inconnu"}
                      {conversation.documentCount > 0 ? ` · ${conversation.documentCount} fichier${conversation.documentCount > 1 ? "s" : ""}` : ""}
                    </div>
                  </button>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-9 w-9 shrink-0 justify-self-end text-destructive hover:bg-destructive/10 hover:text-destructive"
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

      <div className="mt-auto border-t border-border pt-3">
        <Button variant="ghost" className="w-full justify-start gap-3 px-2" onClick={onOpenSettings}>
          <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-muted text-sm font-semibold">
            {(state.user?.name ?? "U").slice(0, 1).toUpperCase()}
          </span>
          <span className="min-w-0 flex-1 truncate text-left">{state.user?.name}</span>
          <Settings2 className="h-4 w-4 shrink-0 text-muted-foreground" />
        </Button>
      </div>
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
  const normalizedMessage = normalizeMessage(message)

  if (!normalizedMessage) {
    return null
  }

  if (normalizedMessage.role === "error") {
    return (
      <div className="flex justify-center">
        <div className="max-w-[80%] rounded-lg border border-destructive bg-destructive/10 px-4 py-2 text-sm text-destructive">
          {normalizedMessage.content}
        </div>
      </div>
    )
  }

  const isUser = normalizedMessage.role === "user"
  const hasParts = normalizedMessage.parts.length > 0

  return (
    <div className={cn("flex", isUser ? "justify-end" : "justify-start")}>
      <div
        className={cn(
          "group max-w-[88%] px-4 py-3 text-sm leading-relaxed shadow-sm sm:max-w-[82%]",
          isUser
            ? "rounded-bl-2xl rounded-br-md rounded-tl-2xl rounded-tr-2xl bg-primary text-primary-foreground"
            : "rounded-bl-md rounded-br-2xl rounded-tl-2xl rounded-tr-2xl border border-border bg-muted/70 text-foreground"
        )}
      >
        <div className={cn("mb-1 text-[11px] font-medium uppercase tracking-wide", isUser ? "text-primary-foreground/70" : "text-muted-foreground")}>
          {isUser ? "Vous" : "Assistant"}
        </div>

        {!isUser && normalizedMessage.isLoading && !hasParts ? (
          <ResponseLoader />
        ) : (
          <div className="space-y-3">
            {normalizedMessage.parts.map((part, index) => (
              <MessagePartRenderer
                key={`${normalizedMessage.role}-${index}-${part.type}`}
                part={part}
                isUser={isUser}
              />
            ))}
          </div>
        )}

        {!isUser && normalizedMessage.isStopped ? (
          <div className="mt-3 rounded-md border border-amber-300/70 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/30 dark:text-amber-100">
            Réponse stoppée par l'utilisateur.
          </div>
        ) : null}
      </div>
    </div>
  )
}

function MessagePartRenderer({ part, isUser }) {
  if (part.type === "image") {
    return <MessageImagePart part={part} isUser={isUser} />
  }

  if (part.type === "file") {
    return <MessageFilePart part={part} isUser={isUser} />
  }

  const text = part.text ?? ""

  if (isUser) {
    return <MarkdownContent content={text} compact isUser />
  }

  return (
    <div className="space-y-3">
      {splitThinkSegments(text).map((segment, index) =>
        segment.type === "think" ? (
          <ThinkingSegment key={index} content={segment.content} isStreaming={segment.isStreaming} index={index} />
        ) : (
          <MarkdownContent key={index} content={segment.content} />
        )
      )}
    </div>
  )
}

function MessageImagePart({ part, isUser }) {
  return (
    <figure className="overflow-hidden rounded-2xl border border-black/10 bg-background/70 shadow-sm">
      <a href={part.url} target="_blank" rel="noreferrer" className="block">
        <img
          src={part.url}
          alt={part.alt ?? "Image du message"}
          className="max-h-[28rem] w-full rounded-t-2xl object-cover"
          loading="lazy"
        />
      </a>
      <figcaption
        className={cn(
          "flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-xs",
          isUser ? "bg-primary-foreground/10 text-primary-foreground/80" : "text-muted-foreground"
        )}
      >
        <span className="min-w-0 truncate">{part.alt ?? part.filename ?? "Image"}</span>
        <a href={part.url} target="_blank" rel="noreferrer" className="inline-flex shrink-0 items-center gap-1 font-medium underline underline-offset-4">
          Ouvrir
          <ExternalLink className="h-3.5 w-3.5" />
        </a>
      </figcaption>
    </figure>
  )
}

function MessageFilePart({ part, isUser }) {
  const details = [part.mediaType, formatBytes(part.size)].filter(Boolean).join(" • ")
  const title = part.title ?? part.filename ?? "Document"

  return (
    <div
      className={cn(
        "rounded-2xl border px-3 py-3 shadow-sm",
        isUser
          ? "border-primary-foreground/15 bg-primary-foreground/10 text-primary-foreground"
          : "border-border bg-background/80 text-foreground"
      )}
    >
      <div className="flex items-start gap-3">
        <div
          className={cn(
            "rounded-xl p-2",
            isUser ? "bg-primary-foreground/10 text-primary-foreground" : "bg-muted text-muted-foreground"
          )}
        >
          <File className="h-5 w-5" />
        </div>
        <div className="min-w-0 flex-1">
          <div className="truncate text-sm font-medium">{title}</div>
          {details ? (
            <div className={cn("mt-1 text-xs", isUser ? "text-primary-foreground/75" : "text-muted-foreground")}>
              {details}
            </div>
          ) : null}
          {part.fileId ? (
            <div className={cn("mt-1 truncate text-[11px]", isUser ? "text-primary-foreground/70" : "text-muted-foreground")}>
              {part.fileId}
            </div>
          ) : null}
        </div>
        {part.url ? (
          <a
            href={part.url}
            target="_blank"
            rel="noreferrer"
            className={cn(
              "inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium",
              isUser
                ? "bg-primary-foreground/10 text-primary-foreground hover:bg-primary-foreground/15"
                : "bg-muted text-foreground hover:bg-muted/80"
            )}
          >
            <Download className="h-3.5 w-3.5" />
            Ouvrir
          </a>
        ) : null}
      </div>
    </div>
  )
}

function ResponseLoader() {
  return (
    <div className="flex items-center gap-3 text-sm text-muted-foreground">
      <Loader2 className="h-4 w-4 animate-spin" />
      <span>Le modèle prépare sa réponse</span>
      <span className="flex gap-1" aria-hidden="true">
        <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-current [animation-delay:-0.2s]" />
        <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-current [animation-delay:-0.1s]" />
        <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-current" />
      </span>
    </div>
  )
}

function ThinkingSegment({ content, isStreaming, index }) {
  const trimmedContent = content.trim()

  if (!trimmedContent && !isStreaming) {
    return null
  }

  return (
    <details className="my-3 overflow-hidden rounded-xl border border-indigo-200/70 bg-indigo-50/70 text-indigo-950 transition open:bg-indigo-50 dark:border-indigo-500/30 dark:bg-indigo-950/30 dark:text-indigo-100">
      <summary className="flex cursor-pointer list-none items-start gap-3 p-3 marker:hidden [&::-webkit-details-marker]:hidden">
        <span className="mt-0.5 rounded-full bg-indigo-100 p-1.5 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-200">
          <BrainCircuit className="h-4 w-4" />
        </span>
        <span className="min-w-0 flex-1">
          <span className="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide">
            Thinking {index + 1}
            {isStreaming ? (
              <span className="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-200">
                <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-current" />
                en cours
              </span>
            ) : null}
          </span>
          <span className="mt-1 line-clamp-2 block text-xs leading-5 text-indigo-800/80 dark:text-indigo-100/75">
            {thinkingPreview(trimmedContent)}
          </span>
        </span>
        <ChevronDown className="mt-1 h-4 w-4 shrink-0 text-indigo-700 transition group-open:rotate-180 dark:text-indigo-200" />
      </summary>
      <div className="border-t border-indigo-200/70 bg-background/80 p-3 text-xs text-muted-foreground dark:border-indigo-500/30">
        {trimmedContent ? (
          <MarkdownContent content={trimmedContent} compact />
        ) : (
          <p>Le modèle prépare sa réflexion...</p>
        )}
      </div>
    </details>
  )
}

function MarkdownContent({ content, compact = false, isUser = false }) {
  const blocks = parseMarkdownBlocks(content)

  if (!blocks.length) {
    return null
  }

  return (
    <div className={cn("space-y-3", compact && "space-y-2")}>
      {blocks.map((block, index) => {
        if (block.type === "code") {
          return <CodeBlock key={index} language={block.language} content={block.content} />
        }

        if (block.type === "heading") {
          const HeadingTag = `h${Math.min(block.level + 2, 6)}`
          return (
            <HeadingTag key={index} className="mt-4 first:mt-0 text-base font-semibold leading-snug">
              {renderInlineMarkdown(block.content, `heading-${index}`)}
            </HeadingTag>
          )
        }

        if (block.type === "quote") {
          return (
            <blockquote key={index} className="border-l-4 border-primary/40 pl-3 text-muted-foreground">
              {renderInlineMarkdown(block.content, `quote-${index}`)}
            </blockquote>
          )
        }

        if (block.type === "list") {
          const ListTag = block.ordered ? "ol" : "ul"
          return (
            <ListTag
              key={index}
              className={cn(
                "space-y-1 pl-5",
                block.ordered ? "list-decimal" : "list-disc"
              )}
            >
              {block.items.map((item, itemIndex) => (
                <li key={itemIndex}>{renderInlineMarkdown(item, `list-${index}-${itemIndex}`)}</li>
              ))}
            </ListTag>
          )
        }

        if (block.type === "table") {
          return <MarkdownTable key={index} block={block} isUser={isUser} tableIndex={index} />
        }

        return (
          <p key={index} className="leading-7">
            {renderInlineMarkdown(block.content, `paragraph-${index}`)}
          </p>
        )
      })}
    </div>
  )
}

function MarkdownTable({ block, isUser, tableIndex }) {
  const columnCount = Math.max(block.headers.length, ...block.rows.map((row) => row.length))

  return (
    <div className="overflow-x-auto rounded-xl border border-border bg-background/80 shadow-sm">
      <table className="min-w-full border-collapse text-left text-sm">
        <thead className={cn(isUser ? "bg-primary-foreground/10" : "bg-muted/80")}>
          <tr>
            {Array.from({ length: columnCount }).map((_, columnIndex) => (
              <th
                key={columnIndex}
                scope="col"
                className={cn(
                  "border-b border-border px-3 py-2 align-top text-xs font-semibold uppercase text-muted-foreground",
                  tableAlignmentClass(block.alignments[columnIndex])
                )}
              >
                {renderInlineMarkdown(block.headers[columnIndex] ?? "", `table-${tableIndex}-head-${columnIndex}`)}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {block.rows.map((row, rowIndex) => (
            <tr key={rowIndex} className="border-b border-border last:border-0">
              {Array.from({ length: columnCount }).map((_, columnIndex) => (
                <td
                  key={columnIndex}
                  className={cn(
                    "px-3 py-2 align-top text-foreground",
                    tableAlignmentClass(block.alignments[columnIndex])
                  )}
                >
                  {renderInlineMarkdown(row[columnIndex] ?? "", `table-${tableIndex}-row-${rowIndex}-${columnIndex}`)}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function tableAlignmentClass(alignment) {
  if (alignment === "center") return "text-center"
  if (alignment === "right") return "text-right"

  return "text-left"
}

function CodeBlock({ language, content }) {
  const [isCopied, setIsCopied] = useState(false)
  const resetCopiedTimerRef = useRef(null)

  useEffect(() => {
    setIsCopied(false)

    return () => {
      if (resetCopiedTimerRef.current) {
        window.clearTimeout(resetCopiedTimerRef.current)
      }
    }
  }, [content])

  async function copyCode() {
    await navigator.clipboard?.writeText(content)
    setIsCopied(true)

    if (resetCopiedTimerRef.current) {
      window.clearTimeout(resetCopiedTimerRef.current)
    }

    resetCopiedTimerRef.current = window.setTimeout(() => {
      setIsCopied(false)
    }, 1600)
  }

  return (
    <div className="overflow-hidden rounded-xl border border-border bg-zinc-950 text-zinc-50 shadow-sm">
      <div className="flex items-center justify-between border-b border-white/10 bg-white/5 px-3 py-2">
        <span className="text-xs font-medium text-zinc-300">{language || "code"}</span>
        <button
          type="button"
          onClick={copyCode}
          className="inline-flex min-w-20 items-center justify-center gap-1 rounded-md px-2 py-1 text-xs text-zinc-300 transition hover:bg-white/10 hover:text-white"
          aria-live="polite"
        >
          {isCopied ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
          {isCopied ? uiText("Copied") : uiText("Copy")}
        </button>
      </div>
      <pre className="overflow-x-auto p-4 text-[13px] leading-6">
        <code>{content}</code>
      </pre>
    </div>
  )
}

function TokenCounter({ selectedModel, tokensUsed, tokenLimit, percentage }) {
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
          <span className="absolute right-1.5 top-1/2 hidden -translate-y-1/2 rounded-full bg-background/90 px-1.5 py-0.5 text-[10px] font-semibold text-foreground shadow-sm ring-1 ring-border lg:inline">
            {percentage}%
          </span>
        </div>
      </div>
    </div>
  )
}

function pluralizeDocument(count, singular = "document") {
  return `${count} ${singular}${count > 1 ? "s" : ""}`
}

function RagContextNotice({ visible, enabled, selectedCollection, collectionStats, conversationDocumentCount }) {
  const selectedCollectionCount = selectedCollection
    ? collectionStats?.[selectedCollection]?.documentCount ?? 0
    : 0

  if (!visible) {
    return null
  }

  let message = "RAG activé."
  let tone = "border-border bg-muted/50 text-muted-foreground"

  if (enabled && selectedCollection) {
    message = `RAG actif sur la collection "${selectedCollection}" · ${pluralizeDocument(selectedCollectionCount)}.`
    tone = selectedCollectionCount > 0
      ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200"
      : "border-amber-400/40 bg-amber-100/70 text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/30 dark:text-amber-100"
  } else if (enabled && conversationDocumentCount > 0) {
    message = `RAG actif sur les fichiers de cette conversation · ${pluralizeDocument(conversationDocumentCount, "fichier")}.`
    tone = "border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200"
  } else if (enabled) {
    message = "RAG actif, mais aucune collection n'est sélectionnée et cette conversation n'a pas encore de fichier."
    tone = "border-amber-400/40 bg-amber-100/70 text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/30 dark:text-amber-100"
  }

  return (
    <div className={cn("mb-3 rounded-md border px-3 py-2 text-xs", tone)}>
      {message}
    </div>
  )
}

function RagControls({ enabled, selectedCollection, collectionStats, conversationDocumentCount, onToggle }) {
  const selectedCollectionCount = selectedCollection
    ? collectionStats?.[selectedCollection]?.documentCount ?? 0
    : 0
  const detail = selectedCollection
    ? `${selectedCollection} · ${pluralizeDocument(selectedCollectionCount)}`
    : conversationDocumentCount > 0
    ? `Conversation · ${pluralizeDocument(conversationDocumentCount, "fichier")}`
    : "Conversation · aucun fichier"

  return (
    <div className="flex flex-wrap items-center justify-between gap-4 lg:justify-end">
      <div className="hidden min-w-56 lg:block">
        <div className="text-sm font-medium">Mode RAG</div>
        <div className="text-xs text-muted-foreground">{detail}</div>
      </div>
      <Switch checked={enabled} onCheckedChange={onToggle} aria-label="Mode RAG" />
    </div>
  )
}
