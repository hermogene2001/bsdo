package com.bsdosale.adapters

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import com.bsdosale.R
import com.bsdosale.models.ChatMessage

class ChatMessageAdapter(
    private val messages: List<ChatMessage>
) : RecyclerView.Adapter<ChatMessageAdapter.MessageViewHolder>() {

    override fun getItemViewType(position: Int): Int {
        return if (messages[position].isSeller) {
            VIEW_TYPE_SELLER
        } else {
            VIEW_TYPE_USER
        }
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): MessageViewHolder {
        val layoutRes = if (viewType == VIEW_TYPE_SELLER) {
            R.layout.item_chat_message_seller
        } else {
            R.layout.item_chat_message_user
        }
        
        val view = LayoutInflater.from(parent.context).inflate(layoutRes, parent, false)
        return MessageViewHolder(view)
    }

    override fun onBindViewHolder(holder: MessageViewHolder, position: Int) {
        holder.bind(messages[position])
    }

    override fun getItemCount(): Int = messages.size

    inner class MessageViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        private val textViewMessage: TextView = itemView.findViewById(R.id.textViewMessage)
        private val textViewTimestamp: TextView = itemView.findViewById(R.id.textViewTimestamp)

        fun bind(message: ChatMessage) {
            textViewMessage.text = message.message
            textViewTimestamp.text = message.timestamp
        }
    }

    companion object {
        private const val VIEW_TYPE_USER = 1
        private const val VIEW_TYPE_SELLER = 2
    }
}