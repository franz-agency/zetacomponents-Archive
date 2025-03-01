<?php
/**
 * File containing the ezcArchiveUstarHeader class.
 *
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 * 
 *   http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 *
 * @package Archive
 * @version //autogentag//
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @access private
 */

/**
 * The ezcArchiveUstarHeader class represents the Tar Ustar header.
 *
 * ezcArchiveUstarHeader can read the header from an ezcArchiveBlockFile or ezcArchiveEntry.
 *
 * The values from the headers are directly accessible via the class properties, and allows
 * reading and writing to specific header values.
 *
 * The entire header can be appended to an ezcArchiveBlockFile again or written to an ezcArchiveFileStructure.
 * Information may get lost, though.
 *
 * The Ustar Header has the following structure:
 *
 * <pre>
 * +--------+------------+-------------------+---------------------------+
 * | Offset | Field size | Property          |  Description              |
 * +--------+------------+-------------------+---------------------------+
 * |  0     | 100        | fileName          | Name of the file          |
 * |  100   | 8          | fileMode          | File mode                 |
 * |  108   | 8          | userId            | Owner user ID             |
 * |  116   | 8          | groupId           | Owner group ID            |
 * |  124   | 12         | fileSize          | Length of file in bytes   |
 * |  136   | 12         | modificationTime  | Modify time of file       |
 * |  148   | 8          | checksum          | Checksum for header       |
 * |  156   | 1          | type              | Indicator for links       |
 * |  157   | 100        | linkName          | Name of linked file       |
 * |  257   | 6          | magic             | USTAR indicator           |
 * |  263   | 2          | version           | USTAR version             |
 * |  265   | 32         | userName          | Owner user name           |
 * |  297   | 32         | groupName         | Owner group name          |
 * |  329   | 8          | deviceMajorNumber | Major device number       |
 * |  337   | 8          | deviceMinorNumber | Minor device number       |
 * |  345   | 155        | filePrefix        | Filename prefix           |
 * |  500   | 12         | -                 | NUL                       |
 * +--------+------------+-------------------+---------------------------+
 * </pre>
 *
 * The columns of the table are:
 * - Offset describes the start position of a header field.
 * - Field size describes the size of the field.
 * - Property is the name of the property that will be set by the header field.
 * - Description explains what this field describes.
 *
 *
 * @package Archive
 * @version //autogentag//
 * @access private
 */
class ezcArchiveUstarHeader extends ezcArchiveV7Header
{
    /**
     * Sets the property $name to $value.
     *
     * @throws ezcBasePropertyNotFoundException if the property does not exist.
     * @param string $name
     * @param mixed $value
     * @return void
     * @ignore
     */
    public function __set( $name, $value )
    {
        switch ( $name )
        {
            case "magic":
            case "version":
            case "userName":
            case "groupName":
            case "deviceMajorNumber":
            case "deviceMinorNumber":
            case "filePrefix":
                $this->properties[$name] = $value;
                return;
        }

        return parent::__set( $name, $value );
    }

    /**
     * Returns the value of the property $name.
     *
     * @throws ezcBasePropertyNotFoundException if the property does not exist.
     * @param string $name
     * @return mixed
     * @ignore
     */
    public function __get($name)
    {
        return match ($name) {
            "magic", "version", "userName", "groupName", "deviceMajorNumber", "deviceMinorNumber", "filePrefix" => $this->properties[ $name ],
            default => parent::__get( $name ),
        };
    }

    /**
     * Creates and initializes a new header.
     *
     * If the ezcArchiveBlockFile $file is null then the header will be empty.
     * When an ezcArchiveBlockFile is given, the block position should point to the header block.
     * This header block will be read from the file and initialized in this class.
     *
     * @param ezcArchiveBlockFile $file
     */
    public function __construct( ezcArchiveBlockFile $file = null )
    {
       // Offset | Field size |  Description
        // ----------------------------------
        //  0     | 100        | Name of file
        //  100   | 8          | File mode
        //  108   | 8          | Owner user ID
        //  116   | 8          | Owner group ID
        //  124   | 12         | Length of file in bytes
        //  136   | 12         | Modify time of file
        //  148   | 8          | Checksum for header
        //  156   | 1          | Type flag.
        //  157   | 100        | Name of linked file
        //  257   | 6          | USTAR indicator.
        //  263   | 2          | USTAR version.
        //  265   | 32         | Owner user name.
        //  297   | 32         | Owner group name.
        //  329   | 8          | Major device number.
        //  337   | 8          | Minor device number.
        //  345   | 155        | Filename prefix.
        //  500   | 12         | NUL.

        if ( !is_null( $file ) )
        {
            parent::__construct( $file );

            // Decode the rest.
            $formatCode = version_compare( PHP_VERSION, '5.5.0', '<' ) ? 'a' : 'Z';
            $decoded = unpack( "@257/{$formatCode}6magic/{$formatCode}2version/{$formatCode}32userName/{$formatCode}32groupName/{$formatCode}8deviceMajorNumber/{$formatCode}8deviceMinorNumber/{$formatCode}155filePrefix", $file->current() );

            // Append the decoded array to the header.
            $this->properties = array_merge( $this->properties, $decoded );

            $this->handleOwner();
         }
    }

    /**
     * This method sets the correct owner in the headers.
     *
     * This only works if PHP has the posix extension compiled in and
     * when the effective user ID is 0 (root).
     */
    protected function handleOwner()
    {
        if ( !ezcBaseFeatures::hasFunction( 'posix_getpwuid' ) )
        {
            return;
        }

        $t =& $this->properties;

        if ( posix_geteuid() === 0 && isset( $t['userName'] ) && $t['userName'] !== '' )
        {
            if ( ( $userName = posix_getpwnam( $t['userName'] ) ) !== false )
            {
                $t['userId'] = $userName['uid'];
            }
            if ( ( $groupName = posix_getgrnam( $t['groupName'] ) ) !== false )
            {
                $t['groupId'] = $groupName['gid'];
            }
        }
    }

    /**
     * Sets this header with the values from the ezcArchiveEntry $entry.
     *
     * The values that are possible to set from the ezcArchiveEntry $entry are set in this header.
     * The properties that may change are: fileName, fileMode, userId, groupId, fileSize, modificationTime,
     * linkName, and type.
     *
     * @param ezcArchiveEntry $entry
     * @return void
     */
    public function setHeaderFromArchiveEntry( ezcArchiveEntry $entry )
    {
        $this->splitFilePrefix( $entry->getPath( false ), $file,  $filePrefix );
        $this->fileName = $file;
        $this->filePrefix = $filePrefix;

        $this->fileMode = $entry->getPermissions();
        $this->userId = $entry->getUserId();
        $this->groupId = $entry->getGroupId();
        $this->fileSize = $entry->getSize();
        $this->modificationTime = $entry->getModificationTime();
        $this->linkName = $entry->getLink( false );

        $this->type = match ($entry->getType()) {
            ezcArchiveEntry::IS_FILE => 0,
            ezcArchiveEntry::IS_LINK => 1,
            ezcArchiveEntry::IS_SYMBOLIC_LINK => 2,
            ezcArchiveEntry::IS_CHARACTER_DEVICE => 3,
            ezcArchiveEntry::IS_BLOCK_DEVICE => 4,
            ezcArchiveEntry::IS_DIRECTORY => 5,
            ezcArchiveEntry::IS_FIFO => 6,
            default => "",
        };

        $this->deviceMajorNumber = $entry->getMajor();
        $this->deviceMinorNumber = $entry->getMinor();

        $length = strlen( (string) $this->fileName );

        if ( $entry->getType() == ezcArchiveEntry::IS_DIRECTORY )
        {
           // Make sure that the filename ends with a slash.
           if ( $this->fileName[ $length - 1] != "/" )
           {
               $this->fileName .= "/";
           }
        }
        else
        {
           if ( $this->fileName[ $length - 1] == "/" )
           {
               $this->fileName = substr( (string) $this->fileName, 0, -1 ); // Remove last character.
           }
        }
    }

    /**
     * Splits the path $path, if it exceeds 100 tokens, into two parts: $file and $filePrefix.
     *
     * If the path contains more than 100 tokens, it will put the directory name in the $filePrefix and
     * the fileName into $file.
     * This is the same method as Gnu Tar splits the file and file prefix.
     *
     * @throws ezcArchiveIoException if the file name cannot be written to the archive.
     * @param string $path
     * @param string &$file
     * @param string &$filePrefix
     * @return void
     */
    protected function splitFilePrefix( $path, &$file, &$filePrefix )
    {
        if ( strlen( $path ) > 100 )
        {
            $filePrefix = dirname( $path );
            $file = basename( $path );

            if ( strlen( $filePrefix )  > 155 || strlen( $file ) > 100 )
            {
                throw new ezcArchiveIoException( "Filename too long: $path" );
            }
        }
        else
        {
            $filePrefix = "";
            $file = $path;
        }
    }

    /**
     * Serializes this header and appends it to the given ezcArchiveBlockFile $archiveFile.
     *
     * @param ezcArchiveBlockFile $archiveFile
     * @return void
     */
    public function writeEncodedHeader( ezcArchiveBlockFile $archiveFile )
    {
        // Offset | Field size |  Description
        // ----------------------------------
        //  0     | 100        | Name of file
        //  100   | 8          | File mode
        //  108   | 8          | Owner user ID
        //  116   | 8          | Owner group ID
        //  124   | 12         | Length of file in bytes
        //  136   | 12         | Modify time of file
        //  148   | 8          | Checksum for header
        //  156   | 1          | Type flag.
        //  157   | 100        | Name of linked file
        //  257   | 6          | USTAR indicator.
        //  263   | 2          | USTAR version.
        //  265   | 32         | Owner user name.
        //  297   | 32         | Owner group name.
        //  329   | 8          | Major device number.
        //  337   | 8          | Minor device number.
        //  345   | 155        | Filename prefix.
        //  500   | 12         | NUL.
        if ( ezcBaseFeatures::hasFunction( 'posix_getpwuid' ) )
        {
            $posixName = ( posix_getpwuid( $this->userId ) );
            $posixGroup = ( posix_getgrgid( $this->groupId ) );
        }
        else
        {
            $posixName['name']= 'nobody';
            $posixGroup['name']= 'nogroup';
        }

        $enc = pack( "a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12",
            $this->fileName,
            str_pad( (string) $this->fileMode, 7, "0", STR_PAD_LEFT),
            str_pad( decoct( $this->userId ), 7, "0", STR_PAD_LEFT),
            str_pad( decoct( $this->groupId ), 7, "0", STR_PAD_LEFT),
            str_pad( decoct( $this->fileSize ), 11, "0", STR_PAD_LEFT),
            str_pad( decoct( $this->modificationTime ), 11, "0", STR_PAD_LEFT),
            "        ",
            $this->type,
            $this->linkName,
            "ustar",
            "00",
            $posixName["name"],
            $posixGroup["name"],
            sprintf( "%07s", decoct( $this->deviceMajorNumber ) ),
            sprintf( "%07s", decoct( $this->deviceMinorNumber ) ),
            $this->filePrefix,
            "" );

        $enc = $this->setChecksum( $enc );

        $archiveFile->append( $enc );
    }

    /**
     * Updates the given ezcArchiveFileStructure $struct with the values from this header.
     *
     * The values that can be set in the archiveFileStructure are: path, gid, uid, type, link, mtime, mode, and size.
     *
     * @throws ezcArchiveValueException
     *         if trying to set the structure type to the reserved type
     * @param ezcArchiveFileStructure &$struct
     * @return void
     */
    public function setArchiveFileStructure( ezcArchiveFileStructure &$struct )
    {
        parent::setArchiveFileStructure( $struct );

        if ( $this->filePrefix != '' )
        {
            $struct->path = $this->filePrefix . DIRECTORY_SEPARATOR . $this->fileName;
        }
        else
        {
            $struct->path = $this->fileName;
        }
        $struct->major = $this->deviceMajorNumber;
        $struct->minor = $this->deviceMinorNumber;
        $struct->userName = $this->userName;
        $struct->groupName = $this->groupName;

        // Override the link type.
        switch ( $this->type )
        {
            case "\0":
            case 0:
                $struct->type = ezcArchiveEntry::IS_FILE;
                break;

            case 1:
                $struct->type = ezcArchiveEntry::IS_LINK;
                break;

            case 2:
                $struct->type = ezcArchiveEntry::IS_SYMBOLIC_LINK;
                break;

            case 3:
                $struct->type = ezcArchiveEntry::IS_CHARACTER_DEVICE;
                break;

            case 4:
                $struct->type = ezcArchiveEntry::IS_BLOCK_DEVICE;
                break;

            case 5:
                $struct->type = ezcArchiveEntry::IS_DIRECTORY;
                break;

            case 6:
                $struct->type = ezcArchiveEntry::IS_FIFO;
                break;

            case 7:
                $struct->type = ezcArchiveEntry::IS_RESERVED;
                break;
        }

        if ( $struct->type == ezcArchiveEntry::IS_RESERVED )
        {
            throw new ezcArchiveValueException( $struct->type, " < " . ezcArchiveEntry::IS_RESERVED );
        }
    }
}
?>
